<?php

namespace App\Services;

use App\Enums\CategorySource;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\ScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Setting up scheduled transactions and moving them along: paying one creates
 * the real transaction, skipping one only moves the due date.
 */
class ScheduledTransactionService
{
    public function __construct(private readonly ManualBalanceAdjuster $balances) {}

    /**
     * @param  array<string, mixed>  $attributes  validated by StoreScheduledTransactionRequest
     */
    public function create(User $user, array $attributes): ScheduledTransaction
    {
        return $user->scheduledTransactions()->create($this->withDerivedAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes  validated by StoreScheduledTransactionRequest
     */
    public function update(ScheduledTransaction $scheduled, array $attributes): ScheduledTransaction
    {
        $scheduled->update($this->withDerivedAttributes($attributes));

        return $scheduled;
    }

    /**
     * Record the occurrence that is due as a real transaction, dated the day it
     * was paid, and move the schedule to its next due date.
     */
    public function pay(ScheduledTransaction $scheduled, CarbonInterface $paidOn): Transaction
    {
        return DB::transaction(function () use ($scheduled, $paidOn): Transaction {
            $transaction = Transaction::query()->create([
                'user_id' => $scheduled->user_id,
                'account_id' => $scheduled->account_id,
                'category_id' => $scheduled->category_id,
                'category_source' => $scheduled->category_id === null ? null : CategorySource::Manual,
                'description' => $scheduled->description,
                'transaction_date' => $paidOn->toDateString(),
                'amount' => $scheduled->amount,
                'currency_code' => $scheduled->currency_code,
                'notes' => $scheduled->notes,
                'source' => TransactionSource::ManuallyCreated,
            ]);

            // Only shifts accounts that own their balance; a connected account
            // keeps the figure its bank reports.
            $this->balances->applyCreatedTransaction($transaction);

            $this->advance($scheduled);

            return $transaction;
        });
    }

    /**
     * Let the occurrence that is due go by without recording anything.
     */
    public function skip(ScheduledTransaction $scheduled): void
    {
        $this->advance($scheduled);
    }

    /**
     * Move to the next due date, or close the schedule when nothing follows:
     * it only happened once, or the next occurrence falls past its end date.
     */
    private function advance(ScheduledTransaction $scheduled): void
    {
        $next = $scheduled->frequency->following($scheduled->next_due_date, $scheduled->anchor_day);

        if ($next === null || ($scheduled->end_date !== null && $next->gt($scheduled->end_date))) {
            $scheduled->update(['completed_at' => now()]);

            return;
        }

        $scheduled->update(['next_due_date' => $next]);
    }

    /**
     * The currency is the account's own, and the day of the month the schedule
     * keeps returning to is the one of the due date the user picked.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withDerivedAttributes(array $attributes): array
    {
        return [
            ...$attributes,
            'currency_code' => Account::query()->whereKey($attributes['account_id'])->value('currency_code'),
            'anchor_day' => Carbon::parse($attributes['next_due_date'])->day,
        ];
    }
}
