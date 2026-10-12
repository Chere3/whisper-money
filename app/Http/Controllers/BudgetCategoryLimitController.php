<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBudgetCategoryLimitsRequest;
use App\Models\Budget;
use App\Services\BudgetCategoryLimitService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class BudgetCategoryLimitController extends Controller
{
    use AuthorizesRequests;

    public function update(
        UpdateBudgetCategoryLimitsRequest $request,
        Budget $budget,
        BudgetCategoryLimitService $categoryLimits,
    ): RedirectResponse {
        $this->authorize('update', $budget);

        $categoryLimits->update($budget, $request->validated('limits'));

        return redirect()->route('budgets.show', $budget);
    }
}
