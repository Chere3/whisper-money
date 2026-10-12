<?php

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DailySpendingService;
use Carbon\Carbon;

beforeEach(function () {
    // A Thursday.
    Carbon::setTestNow('2026-03-12');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function spendOn(User $user, string $date, int $amount, array $attributes = []): Transaction
{
    $account = Account::query()->where('user_id', $user->id)->first()
        ?? Account::factory()->create(['user_id' => $user->id]);
    $category = Category::query()->where('user_id', $user->id)->where('type', CategoryType::Expense)->first()
        ?? Category::factory()->create(['user_id' => $user->id, 'type' => CategoryType::Expense]);

    return Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => $date,
        'amount' => -$amount,
        ...$attributes,
    ]);
}

test('spending is added up per day', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    spendOn($user, '2026-03-10', 1200);
    spendOn($user, '2026-03-10', 800);
    spendOn($user, '2026-03-11', 500);

    $days = app(DailySpendingService::class)->forPeriod($user->id, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-12'));

    expect($days)->toBe(['2026-03-10' => 2000, '2026-03-11' => 500]);
});

test('only expenses of the user inside the window count', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $income = Category::factory()->create(['user_id' => $user->id, 'type' => CategoryType::Income]);
    $transfer = Category::factory()->create(['user_id' => $user->id, 'type' => CategoryType::Transfer]);

    spendOn($user, '2026-03-10', 1200);
    spendOn($user, '2026-03-10', -90000, ['category_id' => $income->id]);
    spendOn($user, '2026-03-10', 30000, ['category_id' => $transfer->id]);
    spendOn($user, '2026-03-10', 700, ['category_id' => null]);
    spendOn($user, '2026-02-28', 400);
    spendOn(User::factory()->create(), '2026-03-10', 5000);

    $days = app(DailySpendingService::class)->forPeriod($user->id, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-12'));

    expect($days)->toBe(['2026-03-10' => 1200]);
});

test('a day whose refunds outweigh its spending is left out', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    spendOn($user, '2026-03-10', 1200);
    spendOn($user, '2026-03-10', -2000);

    $days = app(DailySpendingService::class)->forPeriod($user->id, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-12'));

    expect($days)->toBe([]);
});

test('the calendar covers thirteen whole weeks ending today', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    spendOn($user, '2025-12-15', 300); // The first Monday of the window.
    spendOn($user, '2025-12-14', 900); // The day before it.
    spendOn($user, '2026-03-12', 100);

    $calendar = app(DailySpendingService::class)->calendar($user->id, today());

    expect($calendar)->toBe([
        'from' => '2025-12-15',
        'to' => '2026-03-12',
        'days' => ['2025-12-15' => 300, '2026-03-12' => 100],
    ]);
});

test('the dashboard hands the calendar over as a deferred prop', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    spendOn($user, '2026-03-10', 1200);

    $this->actingAs($user)
        ->get(route('dashboard'), [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'dashboard',
            'X-Inertia-Partial-Data' => 'spendingCalendar',
        ])
        ->assertOk()
        ->assertJsonPath('props.spendingCalendar.from', '2025-12-15')
        ->assertJsonPath('props.spendingCalendar.to', '2026-03-12')
        ->assertJsonPath('props.spendingCalendar.days', ['2026-03-10' => 1200]);
});
