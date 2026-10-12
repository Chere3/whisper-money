<?php

namespace App\Http\Requests;

use App\Enums\DebtDirection;
use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a debt and editing one take the same fields.
 */
class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $debt = $this->route('debt');

        return [
            'direction' => ['required', Rule::enum(DebtDirection::class)],
            'counterparty' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            // Editing cannot bring the total below what was already paid back.
            'amount' => ['required', 'integer', 'min:'.max(1, $debt instanceof Debt ? $debt->paid_amount : 1)],
            'lent_on' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:lent_on'],
        ];
    }
}
