<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayScheduledTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The day the money actually moved; today when left out. Never the
            // future: a transaction is money that already moved.
            'paid_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
