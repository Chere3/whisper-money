<?php

use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('a user records money they lent', function () {
    $user = User::factory()->create(['onboarded_at' => now(), 'currency_code' => 'EUR']);

    $this->actingAs($user)
        ->post(route('debts.store'), [
            'direction' => 'lent',
            'counterparty' => 'Ana',
            'description' => 'Concert tickets',
            'amount' => 12000,
            'lent_on' => '2026-03-01',
            'due_date' => '2026-04-01',
        ])
        ->assertRedirect(route('budgets.index'));

    $debt = $user->debts()->sole();

    expect($debt->direction)->toBe(DebtDirection::Lent)
        ->and($debt->counterparty)->toBe('Ana')
        ->and($debt->amount)->toBe(12000)
        ->and($debt->paid_amount)->toBe(0)
        // In the user's own currency.
        ->and($debt->currency_code)->toBe('EUR')
        ->and($debt->isSettled())->toBeFalse()
        ->and($debt->space_id)->toBe($user->fresh()->current_space_id)
        // A ledger of its own: nothing lands in the transactions.
        ->and(Transaction::query()->count())->toBe(0);
});

test('a debt needs a person, a positive amount and a sensible due date', function (array $overrides, string $error) {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('debts.store'), [
            'direction' => 'borrowed',
            'counterparty' => 'Ana',
            'amount' => 12000,
            'lent_on' => '2026-03-01',
            ...$overrides,
        ])
        ->assertSessionHasErrors($error);

    expect(Debt::query()->count())->toBe(0);
})->with([
    'no person' => [['counterparty' => ''], 'counterparty'],
    'zero amount' => [['amount' => 0], 'amount'],
    'negative amount' => [['amount' => -5], 'amount'],
    'unknown direction' => [['direction' => 'gift'], 'direction'],
    'due before it was lent' => [['due_date' => '2026-02-01'], 'due_date'],
]);

test('a partial payment lowers what is left and keeps the debt open', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $debt = Debt::factory()->create(['user_id' => $user->id, 'amount' => 12000]);

    $this->actingAs($user)
        ->post(route('debts.payments.store', $debt), ['amount' => 5000])
        ->assertRedirect(route('budgets.index'));

    expect($debt->fresh())
        ->paid_amount->toBe(5000)
        ->remainingAmount()->toBe(7000)
        ->isSettled()->toBeFalse();
});

test('a payment without an amount settles the debt', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $debt = Debt::factory()->create(['user_id' => $user->id, 'amount' => 12000, 'paid_amount' => 2000]);

    $this->actingAs($user)->post(route('debts.payments.store', $debt))->assertRedirect();

    expect($debt->fresh())
        ->paid_amount->toBe(12000)
        ->isSettled()->toBeTrue();
});

test('a payment never counts for more than was owed', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $debt = Debt::factory()->create(['user_id' => $user->id, 'amount' => 12000, 'paid_amount' => 10000]);

    $this->actingAs($user)->post(route('debts.payments.store', $debt), ['amount' => 9999])->assertRedirect();

    expect($debt->fresh())
        ->paid_amount->toBe(12000)
        ->isSettled()->toBeTrue();
});

test('a settled debt takes no more payments', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $debt = Debt::factory()->settled()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('debts.payments.store', $debt), ['amount' => 100])->assertForbidden();
});

test('editing cannot bring the total below what was already paid, and raising it reopens the debt', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $debt = Debt::factory()->create(['user_id' => $user->id, 'amount' => 12000, 'paid_amount' => 12000, 'settled_at' => now()]);
    $payload = ['direction' => 'lent', 'counterparty' => 'Ana', 'lent_on' => '2026-03-01'];

    $this->actingAs($user)
        ->put(route('debts.update', $debt), [...$payload, 'amount' => 11999])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->put(route('debts.update', $debt), [...$payload, 'amount' => 15000])
        ->assertRedirect(route('budgets.index'));

    expect($debt->fresh())
        ->counterparty->toBe('Ana')
        ->amount->toBe(15000)
        ->remainingAmount()->toBe(3000)
        ->isSettled()->toBeFalse();
});

test('a user deletes a debt', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $debt = Debt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('debts.destroy', $debt))->assertRedirect(route('budgets.index'));

    expect(Debt::query()->count())->toBe(0);
});

test('a user cannot touch someone else\'s debt', function (string $method, string $route) {
    $debt = Debt::factory()->create(['counterparty' => 'Ana', 'amount' => 12000]);

    $this->actingAs(User::factory()->create(['onboarded_at' => now()]))
        ->{$method}(route($route, $debt), [
            'direction' => 'lent',
            'counterparty' => 'Hijacked',
            'amount' => 12000,
            'lent_on' => '2026-03-01',
        ])
        ->assertForbidden();

    expect($debt->fresh())
        ->counterparty->toBe('Ana')
        ->paid_amount->toBe(0);
})->with([
    'update' => ['put', 'debts.update'],
    'delete' => ['delete', 'debts.destroy'],
    'pay' => ['post', 'debts.payments.store'],
]);

test('the planning page lists open debts first and never someone else\'s', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    Debt::factory()->settled()->create(['user_id' => $user->id, 'counterparty' => 'Settled']);
    Debt::factory()->create(['user_id' => $user->id, 'counterparty' => 'No due date']);
    Debt::factory()->create(['user_id' => $user->id, 'counterparty' => 'Due later', 'due_date' => '2027-02-01']);
    Debt::factory()->create(['user_id' => $user->id, 'counterparty' => 'Due sooner', 'due_date' => '2027-01-01']);
    Debt::factory()->create(['counterparty' => 'Someone else\'s']);

    $this->actingAs($user)
        ->get(route('budgets.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where(
                'debts',
                fn ($debts) => collect($debts)->pluck('counterparty')->all() === ['Due sooner', 'Due later', 'No due date', 'Settled'],
            )
        );
});
