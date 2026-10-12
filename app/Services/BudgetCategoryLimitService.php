<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\Category;

/**
 * Spending limits a budget sets on its tracked categories, on top of the
 * budget's own total.
 */
class BudgetCategoryLimitService
{
    public function __construct(private readonly CategoryTree $tree) {}

    /**
     * What each tracked category has spent in a period, next to its limit.
     *
     * A tracked parent also counts its children's spending, the same way the
     * budget itself does, so a transaction adds to every tracked category
     * among its own category's ancestors.
     *
     * @return list<array{category_id: string, limit_amount: ?int, spent: int}>
     */
    public function breakdown(Budget $budget, BudgetPeriod $period): array
    {
        $categories = $budget->loadMissing('categories')->categories;

        if ($categories->isEmpty()) {
            return [];
        }

        $period->loadMissing('budgetTransactions.transaction');

        $parentMap = $this->tree->parentMap($budget->user_id);
        $spent = array_fill_keys($categories->pluck('id')->all(), 0);

        foreach ($period->budgetTransactions as $budgetTransaction) {
            $categoryId = $budgetTransaction->transaction?->category_id;

            if ($categoryId === null) {
                continue;
            }

            foreach ($this->tree->ancestorAndSelfIds($budget->user_id, $categoryId, $parentMap) as $ancestorId) {
                if (array_key_exists($ancestorId, $spent)) {
                    $spent[$ancestorId] += $budgetTransaction->amount;
                }
            }
        }

        return $categories
            ->map(function (Category $category) use ($spent): array {
                $limit = $category->getRelationValue('pivot')?->getAttribute('limit_amount');

                return [
                    'category_id' => $category->id,
                    'limit_amount' => $limit === null ? null : (int) $limit,
                    'spent' => $spent[$category->id],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Set or clear the limit of the given tracked categories. Categories left
     * out keep the limit they have.
     *
     * @param  array<int, array{category_id: string, limit_amount: ?int}>  $limits
     */
    public function update(Budget $budget, array $limits): void
    {
        foreach ($limits as $limit) {
            $budget->categories()->updateExistingPivot($limit['category_id'], [
                'limit_amount' => $limit['limit_amount'] ?? null,
            ]);
        }
    }
}
