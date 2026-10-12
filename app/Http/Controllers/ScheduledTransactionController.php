<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayScheduledTransactionRequest;
use App\Http\Requests\StoreScheduledTransactionRequest;
use App\Models\ScheduledTransaction;
use App\Services\ScheduledTransactionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Scheduled transactions live on the Planning page, so every action lands back
 * there. Named route rather than back(): the previous-url redirect resolves to
 * the app's internal host behind a proxy.
 */
class ScheduledTransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ScheduledTransactionService $scheduledTransactions) {}

    public function store(StoreScheduledTransactionRequest $request): RedirectResponse
    {
        $this->scheduledTransactions->create($request->user(), $request->validated());

        return redirect()->route('budgets.index');
    }

    public function update(StoreScheduledTransactionRequest $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);

        $this->scheduledTransactions->update($scheduledTransaction, $request->validated());

        return redirect()->route('budgets.index');
    }

    public function destroy(ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('delete', $scheduledTransaction);

        $scheduledTransaction->delete();

        return redirect()->route('budgets.index');
    }

    public function pay(PayScheduledTransactionRequest $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('advance', $scheduledTransaction);

        $this->scheduledTransactions->pay(
            $scheduledTransaction,
            $request->filled('paid_on') ? Carbon::parse($request->validated('paid_on')) : today(),
        );

        return redirect()->route('budgets.index');
    }

    public function skip(ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('advance', $scheduledTransaction);

        $this->scheduledTransactions->skip($scheduledTransaction);

        return redirect()->route('budgets.index');
    }
}
