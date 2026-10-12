<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Http\Requests\Concerns\ValidatesUserOwnedResources;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransferRequest extends FormRequest
{
    use ValidatesUserOwnedResources;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Both ends hold transactions, so both need a ledger.
        $ownedWithLedger = fn () => $this->userOwned('accounts')->whereIn('type', AccountType::withTransactionLedger());

        return [
            'from_account_id' => ['required', $ownedWithLedger()],
            'to_account_id' => ['required', 'different:from_account_id', $ownedWithLedger()],
            'amount' => ['required', 'integer', 'min:1'],
            // A transaction is money that already moved.
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * One amount leaves and the same amount arrives, which only holds when
     * both accounts are in the same currency.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $currencies = Account::query()
                    ->whereIn('id', [$this->input('from_account_id'), $this->input('to_account_id')])
                    ->distinct()
                    ->pluck('currency_code');

                if ($currencies->count() > 1) {
                    $validator->errors()->add('to_account_id', __('Both accounts must be in the same currency.'));
                }
            },
        ];
    }
}
