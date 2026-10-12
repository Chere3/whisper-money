<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Models\TransactionAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransactionAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Receipts and invoices: a photo or a PDF, up to 5 MB.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Transaction $transaction */
                $transaction = $this->route('transaction');

                if ($transaction->attachments()->count() >= TransactionAttachment::MAX_PER_TRANSACTION) {
                    $validator->errors()->add('file', __('A transaction can carry up to :count attachments.', [
                        'count' => TransactionAttachment::MAX_PER_TRANSACTION,
                    ]));
                }
            },
        ];
    }
}
