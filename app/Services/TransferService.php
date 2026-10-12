<?php

namespace App\Services;

use App\Enums\CategorySource;
use App\Enums\CategoryType;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moving money between two of the user's own accounts: one transaction leaving
 * the first, one arriving in the second, tied together by a shared id.
 */
class TransferService
{
    public function __construct(private readonly ManualBalanceAdjuster $balances) {}

    /**
     * @param  array{from_account_id: string, to_account_id: string, amount: int, transaction_date: string, description?: ?string}  $attributes  validated by StoreTransferRequest
     * @return array{0: Transaction, 1: Transaction} the outgoing side, then the incoming one
     */
    public function create(User $user, array $attributes): array
    {
        $from = Account::query()->findOrFail($attributes['from_account_id']);
        $to = Account::query()->findOrFail($attributes['to_account_id']);

        // Filed under a transfer category when the user has one, which is what
        // keeps the pair out of income and spending.
        $categoryId = Category::query()
            ->where('user_id', $user->id)
            ->where('type', CategoryType::Transfer)
            ->orderBy('name')
            ->value('id');

        $shared = [
            'user_id' => $user->id,
            'transfer_id' => (string) Str::uuid7(),
            'category_id' => $categoryId,
            'category_source' => $categoryId === null ? null : CategorySource::Manual,
            'transaction_date' => $attributes['transaction_date'],
            'currency_code' => $from->currency_code,
            'source' => TransactionSource::ManuallyCreated,
        ];
        $description = $attributes['description'] ?? null;

        return DB::transaction(function () use ($shared, $from, $to, $attributes, $description): array {
            $outgoing = Transaction::query()->create([
                ...$shared,
                'account_id' => $from->id,
                'amount' => -$attributes['amount'],
                'description' => $description ?: __('Transfer to :account', ['account' => $to->name]),
            ]);
            $incoming = Transaction::query()->create([
                ...$shared,
                'account_id' => $to->id,
                'amount' => $attributes['amount'],
                'description' => $description ?: __('Transfer from :account', ['account' => $from->name]),
            ]);

            // Only shifts accounts that own their balance; a connected account
            // keeps the figure its bank reports.
            $this->balances->applyCreatedTransaction($outgoing);
            $this->balances->applyCreatedTransaction($incoming);

            return [$outgoing, $incoming];
        });
    }
}
