<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionAttachment>
 */
class TransactionAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'path' => 'transaction-attachments/'.fake()->uuid().'.pdf',
            'name' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
