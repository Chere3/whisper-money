<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\ScheduleFrequency;
use App\Http\Requests\Concerns\ValidatesUserOwnedResources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a scheduled transaction and editing one take the same fields.
 */
class StoreScheduledTransactionRequest extends FormRequest
{
    use ValidatesUserOwnedResources;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only accounts with a ledger: paying a schedule creates a
            // transaction, which a balance-only account cannot hold.
            'account_id' => ['required', $this->userOwned('accounts')->whereIn('type', AccountType::withTransactionLedger())],
            'category_id' => ['nullable', $this->userOwned('categories')],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string'],
            'frequency' => ['required', Rule::enum(ScheduleFrequency::class)],
            'next_due_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:next_due_date'],
        ];
    }
}
