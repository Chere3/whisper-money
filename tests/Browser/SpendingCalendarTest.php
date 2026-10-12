<?php

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('the dashboard draws the spending calendar and a day leads to its transactions', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $account = Account::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['user_id' => $user->id, 'type' => CategoryType::Expense]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'Big night out',
        'transaction_date' => today(),
        'amount' => -12000,
    ]);

    $page = $this->actingAs($user)->visit('/dashboard');
    $page->wait(3); // Wait for the deferred props

    $page->assertSee('Spending calendar')
        ->assertSee('Most expensive day')
        ->assertAttribute('[data-testid="spending-calendar"] a[data-level="4"]', 'href', '/transactions?date_from='.today()->toDateString().'&date_to='.today()->toDateString())
        ->click('[data-testid="spending-calendar"] a[data-level="4"]')
        ->wait(3)
        ->assertPathIs('/transactions')
        ->assertSee('Big night out')
        ->assertNoJavascriptErrors();
});
