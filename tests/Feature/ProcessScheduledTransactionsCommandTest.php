<?php

use App\Enums\AccountType;
use App\Enums\ScheduleFrequency;
use App\Models\Account;
use App\Models\ScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ScheduledTransactionDue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Carbon::setTestNow('2026-03-12');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function scheduledFor(User $user, array $attributes = []): ScheduledTransaction
{
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => AccountType::Checking,
        'currency_code' => 'EUR',
    ]);

    return ScheduledTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'frequency' => ScheduleFrequency::Monthly,
        'next_due_date' => '2026-03-10',
        'anchor_day' => 10,
        ...$attributes,
    ]);
}

test('an automatic schedule records itself on its due date', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $scheduled = scheduledFor($user, ['auto_pay' => true, 'description' => 'Rent', 'amount' => -95000]);

    $this->artisan('scheduled-transactions:process')->assertSuccessful();

    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    expect($transaction->description)->toBe('Rent')
        ->and($transaction->amount)->toBe(-95000)
        // Dated the day it fell due, not the day the run got to it.
        ->and($transaction->transaction_date->toDateString())->toBe('2026-03-10')
        ->and($scheduled->fresh()->next_due_date->toDateString())->toBe('2026-04-10');
});

test('running twice the same day records an occurrence once', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    scheduledFor($user, ['auto_pay' => true]);

    $this->artisan('scheduled-transactions:process');
    $this->artisan('scheduled-transactions:process');

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('an automatic schedule that fell behind catches up one occurrence at a time', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $scheduled = scheduledFor($user, [
        'auto_pay' => true,
        'frequency' => ScheduleFrequency::Weekly,
        'next_due_date' => '2026-02-19',
        'anchor_day' => 19,
    ]);

    $this->artisan('scheduled-transactions:process');

    expect(Transaction::query()->where('user_id', $user->id)->orderBy('transaction_date')->pluck('transaction_date')->map->toDateString()->all())
        ->toBe(['2026-02-19', '2026-02-26', '2026-03-05', '2026-03-12'])
        ->and($scheduled->fresh()->next_due_date->toDateString())->toBe('2026-03-19');
});

test('catching up stops at the end date', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $scheduled = scheduledFor($user, [
        'auto_pay' => true,
        'frequency' => ScheduleFrequency::Weekly,
        'next_due_date' => '2026-02-19',
        'anchor_day' => 19,
        'end_date' => '2026-02-27',
    ]);

    $this->artisan('scheduled-transactions:process');

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and($scheduled->fresh()->isCompleted())->toBeTrue();
});

test('nothing is recorded before the due date or for a schedule that is done', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    scheduledFor($user, ['auto_pay' => true, 'next_due_date' => '2026-03-13', 'anchor_day' => 13]);
    scheduledFor($user, ['auto_pay' => true, 'completed_at' => now()]);

    Notification::fake();
    $this->artisan('scheduled-transactions:process');

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a schedule waiting to be paid reminds its owner once per occurrence', function () {
    Notification::fake();
    $user = User::factory()->create(['onboarded_at' => now()]);
    $scheduled = scheduledFor($user);

    $this->artisan('scheduled-transactions:process');
    $this->artisan('scheduled-transactions:process');

    Notification::assertSentToTimes($user, ScheduledTransactionDue::class, 1);
    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($scheduled->fresh()->next_due_date->toDateString())->toBe('2026-03-10');

    // Skipping moves on to the next occurrence, which gets its own reminder.
    $this->actingAs($user)->post(route('scheduled-transactions.skip', $scheduled));
    Carbon::setTestNow('2026-04-10');
    $this->artisan('scheduled-transactions:process');

    Notification::assertSentToTimes($user, ScheduledTransactionDue::class, 2);
});

test('the reminder shows up in the notification bell and leads to planning', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    scheduledFor($user, ['description' => 'Gym']);

    $this->artisan('scheduled-transactions:process');

    $this->actingAs($user)
        ->get(route('notifications.list'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('notifications.0.kind', 'scheduled_transaction')
                ->where('notifications.0.title', 'Gym is due')
                ->where('notifications.0.url', route('budgets.index'))
        );
});

test('a schedule can be set to record itself', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Checking]);

    $this->actingAs($user)
        ->post(route('scheduled-transactions.store'), [
            'account_id' => $account->id,
            'description' => 'Rent',
            'amount' => -95000,
            'frequency' => 'monthly',
            'next_due_date' => '2026-11-30',
            'auto_pay' => true,
        ])
        ->assertRedirect();

    expect($user->scheduledTransactions()->sole()->auto_pay)->toBeTrue();
});
