<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\ScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;

test('user schedules a transaction and marks it as paid', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => AccountType::Checking,
        'currency_code' => 'USD',
    ]);

    $page = $this->actingAs($user)->visit('/budgets');
    $page->wait(2); // Wait for page to fully load

    $page->assertSee('Upcoming')
        ->assertSee('Nothing scheduled yet')
        ->click('[data-testid="upcoming-transactions"] button:has-text("Schedule")')
        ->wait(1) // Wait for dialog to open
        ->fill('#scheduled-description', 'Netflix')
        ->fill('#scheduled-amount', '15.99')
        ->click('label:has-text("Description")') // Click elsewhere to blur the amount input
        ->wait(1)
        ->click('[role="dialog"] button[type="submit"]')
        ->wait(3) // Wait for form submission
        ->assertSee('Netflix')
        ->assertSee('Due today')
        ->assertNoJavascriptErrors();

    $scheduled = ScheduledTransaction::query()->where('user_id', $user->id)->sole();
    expect($scheduled->amount)->toBe(-1599)
        ->and($scheduled->next_due_date->isToday())->toBeTrue();

    $page->click('button:has-text("Mark as paid")')
        ->wait(3)
        ->assertDontSee('Due today')
        ->assertNoJavascriptErrors();

    $transaction = Transaction::query()->where('user_id', $user->id)->sole();
    expect($transaction->description)->toBe('Netflix')
        ->and($transaction->amount)->toBe(-1599)
        ->and($scheduled->fresh()->next_due_date->isFuture())->toBeTrue();
});
