<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

test('user records a transfer between two accounts', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $checking = Account::factory()->create(['user_id' => $user->id, 'name' => 'Checking', 'type' => AccountType::Checking, 'currency_code' => 'USD']);
    $savings = Account::factory()->create(['user_id' => $user->id, 'name' => 'Savings', 'type' => AccountType::Savings, 'currency_code' => 'USD']);

    $page = $this->actingAs($user)->visit('/transactions');
    $page->wait(2); // Wait for page to fully load

    $page->click('[aria-label="More actions"]')
        ->wait(1)
        ->click('[role="menuitem"]:has-text("Transfer between accounts")')
        ->wait(1) // Wait for dialog to open
        ->click('#transfer-from')
        ->wait(0.5)
        ->click('[role="option"]:has-text("Checking")')
        ->wait(0.5)
        ->click('#transfer-to')
        ->wait(0.5)
        ->click('[role="option"]:has-text("Savings")')
        ->wait(0.5)
        ->fill('#transfer-amount', '250')
        ->click('label:has-text("Amount")') // Click elsewhere to blur the amount input
        ->wait(1)
        ->click('[role="dialog"] button[type="submit"]')
        ->wait(3) // Wait for form submission
        ->assertSee('Transfer to Savings')
        ->assertSee('Transfer from Checking')
        ->assertNoJavascriptErrors();

    expect(Transaction::query()->where('account_id', $checking->id)->sole()->amount)->toBe(-25000)
        ->and(Transaction::query()->where('account_id', $savings->id)->sole()->amount)->toBe(25000);
});
