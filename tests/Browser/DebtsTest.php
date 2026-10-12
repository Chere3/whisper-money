<?php

use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\User;

test('user adds a debt and records a payment that settles it', function () {
    $user = User::factory()->create(['onboarded_at' => now(), 'currency_code' => 'USD']);

    $page = $this->actingAs($user)->visit('/budgets');
    $page->wait(2); // Wait for page to fully load

    $page->assertSee('Lent & borrowed')
        ->click('[data-testid="debts-section"] button:has-text("Add debt")')
        ->wait(1) // Wait for dialog to open
        ->fill('#debt-counterparty', 'Ana')
        ->fill('#debt-amount', '120')
        ->click('label:has-text("To whom")') // Click elsewhere to blur the amount input
        ->wait(1)
        ->click('[role="dialog"] button[type="submit"]')
        ->wait(3) // Wait for form submission
        ->assertSee('Ana')
        ->assertSee('Owes you')
        ->assertNoJavascriptErrors();

    $debt = Debt::query()->where('user_id', $user->id)->sole();
    expect($debt->direction)->toBe(DebtDirection::Lent)
        ->and($debt->amount)->toBe(12000);

    $page->click('button:has-text("Record payment")')
        ->wait(1)
        ->click('[role="dialog"] button[type="submit"]')
        ->wait(3)
        ->assertSee('Settled (1)')
        ->assertNoJavascriptErrors();

    expect($debt->fresh()->isSettled())->toBeTrue();
});
