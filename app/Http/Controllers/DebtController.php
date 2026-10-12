<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordDebtPaymentRequest;
use App\Http\Requests\StoreDebtRequest;
use App\Models\Debt;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/**
 * Debts live on the Planning page, so every action lands back there. Named
 * route rather than back(): the previous-url redirect resolves to the app's
 * internal host behind a proxy.
 */
class DebtController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->debts()->create([
            ...$request->validated(),
            'currency_code' => $user->currency_code ?? 'USD',
        ]);

        return redirect()->route('budgets.index');
    }

    public function update(StoreDebtRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('update', $debt);

        $debt->fill($request->validated());
        // A total raised above what was paid reopens the debt.
        $debt->settled_at = $debt->remainingAmount() === 0 ? ($debt->settled_at ?? now()) : null;
        $debt->save();

        return redirect()->route('budgets.index');
    }

    public function destroy(Debt $debt): RedirectResponse
    {
        $this->authorize('delete', $debt);

        $debt->delete();

        return redirect()->route('budgets.index');
    }

    public function recordPayment(RecordDebtPaymentRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('recordPayment', $debt);

        $debt->recordPayment($request->integer('amount') ?: $debt->remainingAmount());

        return redirect()->route('budgets.index');
    }
}
