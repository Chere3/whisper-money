<?php

use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * @return array{0: User, 1: Budget, 2: BudgetPeriod}
 */
function budgetTrackingCategories(Category ...$categories): array
{
    $user = User::query()->findOrFail($categories[0]->user_id);
    $budget = Budget::factory()->forCategories($categories)->create(['user_id' => $user->id]);
    $period = BudgetPeriod::factory()->create([
        'budget_id' => $budget->id,
        'start_date' => today()->startOfMonth(),
        'end_date' => today()->endOfMonth(),
        'allocated_amount' => 100000,
    ]);

    return [$user, $budget, $period];
}

function spendInPeriod(BudgetPeriod $period, Category $category, int $amount): void
{
    $transaction = Transaction::factory()->create([
        'user_id' => $category->user_id,
        'category_id' => $category->id,
        'amount' => -$amount,
        'transaction_date' => today(),
    ]);

    // Creating the transaction may already have assigned it to the budget.
    BudgetTransaction::query()->updateOrCreate(
        ['transaction_id' => $transaction->id, 'budget_period_id' => $period->id],
        ['amount' => $amount],
    );
}

test('a user can set a limit on a tracked category', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $groceries = Category::factory()->create(['user_id' => $user->id]);
    $dining = Category::factory()->create(['user_id' => $user->id]);
    [, $budget] = budgetTrackingCategories($groceries, $dining);

    $this->actingAs($user)
        ->patch(route('budgets.category-limits.update', $budget), [
            'limits' => [
                ['category_id' => $groceries->id, 'limit_amount' => 30000],
                ['category_id' => $dining->id, 'limit_amount' => null],
            ],
        ])
        ->assertRedirect(route('budgets.show', $budget));

    $this->assertDatabaseHas('budget_category', [
        'budget_id' => $budget->id,
        'category_id' => $groceries->id,
        'limit_amount' => 30000,
    ]);
    $this->assertDatabaseHas('budget_category', [
        'budget_id' => $budget->id,
        'category_id' => $dining->id,
        'limit_amount' => null,
    ]);
});

test('sending null clears a limit and leaves the other categories alone', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $groceries = Category::factory()->create(['user_id' => $user->id]);
    $dining = Category::factory()->create(['user_id' => $user->id]);
    [, $budget] = budgetTrackingCategories($groceries, $dining);
    $budget->categories()->updateExistingPivot($groceries->id, ['limit_amount' => 30000]);
    $budget->categories()->updateExistingPivot($dining->id, ['limit_amount' => 20000]);

    $this->actingAs($user)
        ->patch(route('budgets.category-limits.update', $budget), [
            'limits' => [['category_id' => $groceries->id, 'limit_amount' => null]],
        ])
        ->assertRedirect();

    expect($budget->categories()->get()->pluck('pivot.limit_amount', 'id')->all())->toEqual([
        $groceries->id => null,
        $dining->id => 20000,
    ]);
});

test('a limit cannot be set on a category the budget does not track', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $tracked = Category::factory()->create(['user_id' => $user->id]);
    $untracked = Category::factory()->create(['user_id' => $user->id]);
    [, $budget] = budgetTrackingCategories($tracked);

    $this->actingAs($user)
        ->patch(route('budgets.category-limits.update', $budget), [
            'limits' => [['category_id' => $untracked->id, 'limit_amount' => 5000]],
        ])
        ->assertSessionHasErrors('limits.0.category_id');
});

test('a limit has to be a positive amount', function (mixed $amount) {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $category = Category::factory()->create(['user_id' => $user->id]);
    [, $budget] = budgetTrackingCategories($category);

    $this->actingAs($user)
        ->patch(route('budgets.category-limits.update', $budget), [
            'limits' => [['category_id' => $category->id, 'limit_amount' => $amount]],
        ])
        ->assertSessionHasErrors('limits.0.limit_amount');
})->with([0, -100, 'abc']);

test('a user cannot set limits on someone else\'s budget', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $category = Category::factory()->create(['user_id' => $owner->id]);
    [, $budget] = budgetTrackingCategories($category);

    $this->actingAs(User::factory()->create(['onboarded_at' => now()]))
        ->patch(route('budgets.category-limits.update', $budget), [
            'limits' => [['category_id' => $category->id, 'limit_amount' => 5000]],
        ])
        ->assertForbidden();

    expect($budget->categories()->first()->pivot->limit_amount)->toBeNull();
});

test('an archived budget keeps its limits read-only', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $category = Category::factory()->create(['user_id' => $user->id]);
    [, $budget] = budgetTrackingCategories($category);
    $budget->update(['archived_at' => now()]);

    $this->actingAs($user)
        ->patch(route('budgets.category-limits.update', $budget), [
            'limits' => [['category_id' => $category->id, 'limit_amount' => 5000]],
        ])
        ->assertForbidden();
});

test('the budget page reports what each tracked category spent next to its limit', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $groceries = Category::factory()->create(['user_id' => $user->id]);
    $dining = Category::factory()->create(['user_id' => $user->id]);
    [, $budget, $period] = budgetTrackingCategories($groceries, $dining);
    $budget->categories()->updateExistingPivot($groceries->id, ['limit_amount' => 30000]);

    spendInPeriod($period, $groceries, 12000);
    spendInPeriod($period, $groceries, 3000);
    spendInPeriod($period, $dining, 4500);

    $this->actingAs($user)
        ->get(route('budgets.show', $budget))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('budgets/show')
                ->where('categoryLimits', fn ($limits) => collect($limits)->sortBy('spent')->values()->all() === [
                    ['category_id' => $dining->id, 'limit_amount' => null, 'spent' => 4500],
                    ['category_id' => $groceries->id, 'limit_amount' => 30000, 'spent' => 15000],
                ])
        );
});

test('a tracked parent category counts what its children spent', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $food = Category::factory()->create(['user_id' => $user->id]);
    $groceries = Category::factory()->create(['user_id' => $user->id, 'parent_id' => $food->id]);
    [, $budget, $period] = budgetTrackingCategories($food);

    spendInPeriod($period, $food, 1000);
    spendInPeriod($period, $groceries, 2500);

    $this->actingAs($user)
        ->get(route('budgets.show', $budget))
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('categoryLimits', [
                ['category_id' => $food->id, 'limit_amount' => null, 'spent' => 3500],
            ])
        );
});

test('a budget that tracks no categories has no category limits', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $budget = Budget::factory()->catchAll()->create(['user_id' => $user->id]);
    BudgetPeriod::factory()->create([
        'budget_id' => $budget->id,
        'start_date' => today()->startOfMonth(),
        'end_date' => today()->endOfMonth(),
        'allocated_amount' => 100000,
    ]);

    $this->actingAs($user)
        ->get(route('budgets.show', $budget))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categoryLimits', []));
});
