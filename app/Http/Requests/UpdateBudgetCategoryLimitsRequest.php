<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetCategoryLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Budget $budget */
        $budget = $this->route('budget');

        return [
            'limits' => ['required', 'array'],
            'limits.*.category_id' => [
                'required',
                'distinct',
                Rule::exists('budget_category', 'category_id')->where('budget_id', $budget->id),
            ],
            // No limit is sent as null rather than zero: a category with a zero
            // limit would read as over it from its first transaction.
            'limits.*.limit_amount' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }
}
