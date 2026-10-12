<?php

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-03-12');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Account, 2: Account}
 */
function userWithTwoAccounts(string $toCurrency = 'EUR'): array
{
    $user = User::factory()->create(['onboarded_at' => now()]);
    $checking = Account::factory()->create(['user_id' => $user->id, 'name' => 'Checking', 'type' => AccountType::Checking, 'currency_code' => 'EUR']);
    $savings = Account::factory()->create(['user_id' => $user->id, 'name' => 'Savings', 'type' => AccountType::Savings, 'currency_code' => $toCurrency]);

    return [$user, $checking, $savings];
}

test('a transfer leaves one account and arrives in the other as a linked pair', function () {
    [$user, $checking, $savings] = userWithTwoAccounts();
    $category = Category::factory()->create(['user_id' => $user->id, 'type' => CategoryType::Transfer]);

    $this->actingAs($user)
        ->post(route('transfers.store'), [
            'from_account_id' => $checking->id,
            'to_account_id' => $savings->id,
            'amount' => 25000,
            'transaction_date' => '2026-03-10',
        ])
        ->assertRedirect(route('transactions.index'));

    $outgoing = Transaction::query()->where('account_id', $checking->id)->sole();
    $incoming = Transaction::query()->where('account_id', $savings->id)->sole();

    expect($outgoing->amount)->toBe(-25000)
        ->and($incoming->amount)->toBe(25000)
        ->and($outgoing->description)->toBe('Transfer to Savings')
        ->and($incoming->description)->toBe('Transfer from Checking')
        ->and($outgoing->transfer_id)->not->toBeNull()
        ->and($incoming->transfer_id)->toBe($outgoing->transfer_id)
        ->and($outgoing->transferCounterpart()->is($incoming))->toBeTrue()
        ->and($outgoing->transaction_date->toDateString())->toBe('2026-03-10')
        ->and($outgoing->currency_code)->toBe('EUR')
        ->and($outgoing->source)->toBe(TransactionSource::ManuallyCreated)
        // Filed as a transfer, so it stays out of income and spending.
        ->and($outgoing->category_id)->toBe($category->id)
        ->and($incoming->category_id)->toBe($category->id);
});

test('a transfer keeps the description the user gave it and works without a transfer category', function () {
    [$user, $checking, $savings] = userWithTwoAccounts();

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'amount' => 25000,
        'transaction_date' => '2026-03-10',
        'description' => 'Rainy day fund',
    ])->assertRedirect();

    expect(Transaction::query()->where('user_id', $user->id)->pluck('description')->unique()->all())->toBe(['Rainy day fund'])
        ->and(Transaction::query()->where('user_id', $user->id)->whereNotNull('category_id')->count())->toBe(0);
});

test('a transfer shifts the balance of both accounts', function () {
    [$user, $checking, $savings] = userWithTwoAccounts();
    $checking->balances()->create(['balance_date' => '2026-03-01', 'balance' => 100000]);
    $savings->balances()->create(['balance_date' => '2026-03-01', 'balance' => 5000]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'amount' => 25000,
        'transaction_date' => '2026-03-10',
    ])->assertRedirect();

    expect($checking->balances()->where('balance_date', '2026-03-10')->value('balance'))->toBe(75000)
        ->and($savings->balances()->where('balance_date', '2026-03-10')->value('balance'))->toBe(30000);
});

test('a transfer needs two different accounts of the user, in the same currency', function (Closure $overrides, string $error) {
    [$user, $checking, $savings] = userWithTwoAccounts();
    $dollars = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Checking, 'currency_code' => 'USD']);
    $investment = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Investment, 'currency_code' => 'EUR']);
    $foreign = Account::factory()->create(['type' => AccountType::Checking, 'currency_code' => 'EUR']);

    $this->actingAs($user)
        ->post(route('transfers.store'), [
            'from_account_id' => $checking->id,
            'to_account_id' => $savings->id,
            'amount' => 25000,
            'transaction_date' => '2026-03-10',
            ...$overrides(compact('checking', 'dollars', 'investment', 'foreign')),
        ])
        ->assertSessionHasErrors($error);

    expect(Transaction::query()->count())->toBe(0);
})->with([
    'the same account twice' => [fn (array $a) => ['to_account_id' => $a['checking']->id], 'to_account_id'],
    'another currency' => [fn (array $a) => ['to_account_id' => $a['dollars']->id], 'to_account_id'],
    'a balance-only account' => [fn (array $a) => ['to_account_id' => $a['investment']->id], 'to_account_id'],
    'someone else\'s account' => [fn (array $a) => ['from_account_id' => $a['foreign']->id], 'from_account_id'],
    'no amount' => [fn () => ['amount' => 0], 'amount'],
    'a date in the future' => [fn () => ['transaction_date' => '2026-03-13'], 'transaction_date'],
]);

test('deleting one side of a transfer deletes the other and puts both balances back', function () {
    [$user, $checking, $savings] = userWithTwoAccounts();
    $checking->balances()->create(['balance_date' => '2026-03-01', 'balance' => 100000]);
    $savings->balances()->create(['balance_date' => '2026-03-01', 'balance' => 5000]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'amount' => 25000,
        'transaction_date' => '2026-03-10',
    ]);

    $incoming = Transaction::query()->where('account_id', $savings->id)->sole();

    $this->actingAs($user)
        ->deleteJson(route('transactions.destroy', $incoming), ['update_balance' => true])
        ->assertOk();

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($checking->balances()->where('balance_date', '2026-03-10')->value('balance'))->toBe(100000)
        ->and($savings->balances()->where('balance_date', '2026-03-10')->value('balance'))->toBe(5000);
});

test('deleting an ordinary transaction leaves the others alone', function () {
    [$user, $checking] = userWithTwoAccounts();
    $kept = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $checking->id]);
    $deleted = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $checking->id]);

    $this->actingAs($user)->deleteJson(route('transactions.destroy', $deleted))->assertOk();

    expect(Transaction::query()->where('user_id', $user->id)->pluck('id')->all())->toBe([$kept->id]);
});
