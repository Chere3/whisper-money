<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

test('the transactions page offers a csv export of what is on screen', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $account = Account::factory()->create(['user_id' => $user->id]);
    Transaction::factory()->count(3)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => today(),
    ]);

    $page = $this->actingAs($user)->visit('/transactions');
    $page->wait(2); // Wait for page to fully load

    $page->click('[aria-label="More actions"]')
        ->wait(1)
        ->assertSee('Export to CSV')
        ->assertAttribute('[role="menu"] a[download]', 'href', '/transactions/export')
        ->assertNoJavascriptErrors();

    $page = $this->actingAs($user)->visit('/transactions?search=coffee');
    $page->wait(2);

    $page->click('[aria-label="More actions"]')
        ->wait(1)
        ->assertSee('Export filtered to CSV')
        ->assertAttribute('[role="menu"] a[download]', 'href', '/transactions/export?search=coffee')
        ->assertNoJavascriptErrors();
});
