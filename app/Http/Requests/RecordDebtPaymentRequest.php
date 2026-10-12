<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordDebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Left out, the payment covers everything that is still owed.
            'amount' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
