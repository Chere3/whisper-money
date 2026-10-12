<?php

use App\Enums\AccountType;
use App\Enums\CategorySource;
use App\Enums\ScheduleFrequency;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\ScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Account}
 */
function userWithCheckingAccount(): array
{
    $user = User::factory()->create(['onboarded_at' => now()]);
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => AccountType::Checking,
        'currency_code' => 'EUR',
    ]);

    return [$user, $account];
}

test('a user schedules a recurring transaction', function () {
    [$user, $account] = userWithCheckingAccount();
    $category = Category::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('scheduled-transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Rent',
            'amount' => -95000,
            'notes' => 'Flat',
            'frequency' => 'monthly',
            'next_due_date' => '2026-11-30',
            'end_date' => null,
        ])
        ->assertRedirect(route('budgets.index'));

    $scheduled = $user->scheduledTransactions()->sole();

    expect($scheduled->description)->toBe('Rent')
        ->and($scheduled->amount)->toBe(-95000)
        // The currency is the account's own and the anchor is the due day.
        ->and($scheduled->currency_code)->toBe('EUR')
        ->and($scheduled->anchor_day)->toBe(30)
        ->and($scheduled->frequency)->toBe(ScheduleFrequency::Monthly)
        ->and($scheduled->next_due_date->toDateString())->toBe('2026-11-30')
        ->and($scheduled->space_id)->toBe($user->fresh()->current_space_id);
});

test('a scheduled transaction needs an account with a ledger, an amount and a valid range', function (array $overrides, string $error) {
    [$user, $account] = userWithCheckingAccount();
    $investment = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Investment]);
    $foreign = Account::factory()->create();

    $payload = [
        'account_id' => $account->id,
        'description' => 'Rent',
        'amount' => -95000,
        'frequency' => 'monthly',
        'next_due_date' => '2026-11-30',
        ...$overrides,
    ];
    $payload['account_id'] = match ($payload['account_id']) {
        'investment' => $investment->id,
        'foreign' => $foreign->id,
        default => $payload['account_id'],
    };

    $this->actingAs($user)
        ->post(route('scheduled-transactions.store'), $payload)
        ->assertSessionHasErrors($error);

    expect(ScheduledTransaction::query()->count())->toBe(0);
})->with([
    'balance-only account' => [['account_id' => 'investment'], 'account_id'],
    'someone else\'s account' => [['account_id' => 'foreign'], 'account_id'],
    'zero amount' => [['amount' => 0], 'amount'],
    'unknown frequency' => [['frequency' => 'daily'], 'frequency'],
    'end before the first due date' => [['end_date' => '2026-11-01'], 'end_date'],
    'no description' => [['description' => ''], 'description'],
]);

test('paying a scheduled transaction records it and moves the due date along', function () {
    Carbon::setTestNow('2026-03-12');
    [$user, $account] = userWithCheckingAccount();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $scheduled = ScheduledTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'Gym',
        'amount' => -3500,
        'notes' => 'Annual plan',
        'frequency' => ScheduleFrequency::Monthly,
        'next_due_date' => '2026-03-10',
        'anchor_day' => 10,
    ]);

    $this->actingAs($user)
        ->post(route('scheduled-transactions.pay', $scheduled))
        ->assertRedirect(route('budgets.index'));

    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    expect($transaction->description)->toBe('Gym')
        ->and($transaction->amount)->toBe(-3500)
        ->and($transaction->currency_code)->toBe('EUR')
        ->and($transaction->account_id)->toBe($account->id)
        ->and($transaction->category_id)->toBe($category->id)
        ->and($transaction->category_source)->toBe(CategorySource::Manual)
        ->and($transaction->notes)->toBe('Annual plan')
        ->and($transaction->source)->toBe(TransactionSource::ManuallyCreated)
        // Dated the day it was paid, not the day it was due.
        ->and($transaction->transaction_date->toDateString())->toBe('2026-03-12')
        ->and($transaction->space_id)->toBe($account->space_id)
        ->and($scheduled->fresh()->next_due_date->toDateString())->toBe('2026-04-10')
        ->and($scheduled->fresh()->isCompleted())->toBeFalse();
});

test('a payment can be dated in the past but never in the future', function () {
    Carbon::setTestNow('2026-03-12');
    [$user, $account] = userWithCheckingAccount();
    $scheduled = ScheduledTransaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

    $this->actingAs($user)
        ->post(route('scheduled-transactions.pay', $scheduled), ['paid_on' => '2026-03-13'])
        ->assertSessionHasErrors('paid_on');

    $this->actingAs($user)
        ->post(route('scheduled-transactions.pay', $scheduled), ['paid_on' => '2026-03-09'])
        ->assertRedirect();

    expect(Transaction::query()->where('user_id', $user->id)->sole()->transaction_date->toDateString())->toBe('2026-03-09');
});

test('a paid expense counts against the budget tracking its category', function () {
    Carbon::setTestNow('2026-03-12');
    [$user, $account] = userWithCheckingAccount();
    $user->update(['currency_code' => 'EUR']);
    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
    $budget = Budget::factory()->forCategories($category)->create(['user_id' => $user->id]);
    $period = BudgetPeriod::factory()->create([
        'budget_id' => $budget->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'allocated_amount' => 100000,
    ]);
    $scheduled = ScheduledTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => -3500,
    ]);

    $this->actingAs($user)->post(route('scheduled-transactions.pay', $scheduled))->assertRedirect();

    expect($period->budgetTransactions()->count())->toBe(1);
});

test('paying shifts the balance of a manual account', function () {
    Carbon::setTestNow('2026-03-12');
    [$user, $account] = userWithCheckingAccount();
    $account->balances()->create(['balance_date' => '2026-03-01', 'balance' => 100000]);
    $scheduled = ScheduledTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'amount' => -3500,
    ]);

    $this->actingAs($user)->post(route('scheduled-transactions.pay', $scheduled))->assertRedirect();

    expect($account->balances()->where('balance_date', '2026-03-12')->value('balance'))->toBe(96500);
});

test('skipping moves the due date along without recording anything', function () {
    [$user, $account] = userWithCheckingAccount();
    $scheduled = ScheduledTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'frequency' => ScheduleFrequency::Weekly,
        'next_due_date' => '2026-03-10',
        'anchor_day' => 10,
    ]);

    $this->actingAs($user)
        ->post(route('scheduled-transactions.skip', $scheduled))
        ->assertRedirect(route('budgets.index'));

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($scheduled->fresh()->next_due_date->toDateString())->toBe('2026-03-17');
});

test('a one-off scheduled transaction is done once it is paid', function () {
    [$user, $account] = userWithCheckingAccount();
    $scheduled = ScheduledTransaction::factory()->once()->create(['user_id' => $user->id, 'account_id' => $account->id]);

    $this->actingAs($user)->post(route('scheduled-transactions.pay', $scheduled))->assertRedirect();

    expect($scheduled->fresh()->isCompleted())->toBeTrue();

    // Nothing left to pay or skip.
    $this->actingAs($user)->post(route('scheduled-transactions.pay', $scheduled))->assertForbidden();
    $this->actingAs($user)->post(route('scheduled-transactions.skip', $scheduled))->assertForbidden();

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('a schedule stops when its next occurrence falls past the end date', function () {
    [$user, $account] = userWithCheckingAccount();
    $scheduled = ScheduledTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'frequency' => ScheduleFrequency::Monthly,
        'next_due_date' => '2026-03-10',
        'anchor_day' => 10,
        'end_date' => '2026-04-10',
    ]);

    $this->actingAs($user)->post(route('scheduled-transactions.skip', $scheduled));
    expect($scheduled->fresh()->next_due_date->toDateString())->toBe('2026-04-10')
        ->and($scheduled->fresh()->isCompleted())->toBeFalse();

    $this->actingAs($user)->post(route('scheduled-transactions.skip', $scheduled));
    expect($scheduled->fresh()->isCompleted())->toBeTrue();
});

test('a user edits and deletes a scheduled transaction', function () {
    [$user, $account] = userWithCheckingAccount();
    $scheduled = ScheduledTransaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

    $this->actingAs($user)
        ->put(route('scheduled-transactions.update', $scheduled), [
            'account_id' => $account->id,
            'description' => 'Internet',
            'amount' => -4000,
            'frequency' => 'yearly',
            'next_due_date' => '2027-02-28',
        ])
        ->assertRedirect(route('budgets.index'));

    expect($scheduled->fresh())
        ->description->toBe('Internet')
        ->amount->toBe(-4000)
        ->frequency->toBe(ScheduleFrequency::Yearly)
        ->anchor_day->toBe(28);

    $this->actingAs($user)
        ->delete(route('scheduled-transactions.destroy', $scheduled))
        ->assertRedirect(route('budgets.index'));

    expect(ScheduledTransaction::query()->count())->toBe(0);
});

test('a user cannot touch someone else\'s scheduled transaction', function (string $method, string $route) {
    $scheduled = ScheduledTransaction::factory()->create();
    [$intruder, $account] = userWithCheckingAccount();

    $this->actingAs($intruder)
        ->{$method}(route($route, $scheduled), [
            'account_id' => $account->id,
            'description' => 'Hijacked',
            'amount' => -1,
            'frequency' => 'once',
            'next_due_date' => '2026-11-30',
        ])
        ->assertForbidden();

    expect($scheduled->fresh()->description)->not->toBe('Hijacked')
        ->and(Transaction::query()->count())->toBe(0);
})->with([
    'update' => ['put', 'scheduled-transactions.update'],
    'delete' => ['delete', 'scheduled-transactions.destroy'],
    'pay' => ['post', 'scheduled-transactions.pay'],
    'skip' => ['post', 'scheduled-transactions.skip'],
]);

test('the planning page lists what is still to come, soonest first', function () {
    [$user, $account] = userWithCheckingAccount();
    ScheduledTransaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'description' => 'Later', 'next_due_date' => '2026-12-01']);
    ScheduledTransaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'description' => 'Sooner', 'next_due_date' => '2026-11-01']);
    ScheduledTransaction::factory()->completed()->create(['user_id' => $user->id, 'account_id' => $account->id, 'description' => 'Done']);
    ScheduledTransaction::factory()->create(['description' => 'Someone else\'s']);

    $this->actingAs($user)
        ->get(route('budgets.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('budgets/index')
                ->where('scheduledTransactions', fn ($scheduled) => collect($scheduled)->pluck('description')->all() === ['Sooner', 'Later'])
                ->has('scheduledTransactions.0.account')
                ->has('accounts', 1)
                ->has('categories')
        );
});
