<?php

use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;

test('user can set a spending limit on a tracked category', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $groceries = Category::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);
    $dining = Category::factory()->create(['user_id' => $user->id, 'name' => 'Dining']);
    $budget = Budget::factory()->forCategories([$groceries, $dining])->create([
        'user_id' => $user->id,
        'name' => 'Food',
    ]);
    BudgetPeriod::factory()->create([
        'budget_id' => $budget->id,
        'start_date' => today()->startOfMonth(),
        'end_date' => today()->endOfMonth(),
        'allocated_amount' => 100000,
    ]);

    $page = $this->actingAs($user)->visit("/budgets/{$budget->id}");
    $page->wait(2); // Wait for page to fully load

    $page->assertSee('Category limits')
        ->assertSee('No limit')
        ->click('button:has-text("Set limits")')
        ->wait(1) // Wait for dialog to open
        ->fill("#category-limit-{$groceries->id}-input", '300')
        ->click('[role="dialog"] h2') // Click elsewhere to blur the amount input
        ->wait(1)
        ->click('button:has-text("Save limits")')
        ->wait(3) // Wait for form submission
        ->assertSee('Edit limits')
        ->assertNoJavascriptErrors();

    expect($budget->categories()->get()->pluck('pivot.limit_amount', 'id')->all())->toEqual([
        $groceries->id => 30000,
        $dining->id => null,
    ]);
});
