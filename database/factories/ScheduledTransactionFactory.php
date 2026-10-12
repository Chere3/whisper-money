<?php

namespace Database\Factories;

use App\Enums\ScheduleFrequency;
use App\Models\Account;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledTransaction>
 */
class ScheduledTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dueDate = today()->addDays(fake()->numberBetween(1, 20));

        return [
            'user_id' => User::factory(),
            'account_id' => fn (array $attributes) => Account::factory()->state(['user_id' => $attributes['user_id']]),
            'category_id' => null,
            'description' => fake()->words(2, true),
            'amount' => -fake()->numberBetween(500, 50000),
            // A schedule is always in its account's own currency.
            'currency_code' => fn (array $attributes) => Account::query()->whereKey($attributes['account_id'])->value('currency_code'),
            'notes' => null,
            'frequency' => ScheduleFrequency::Monthly,
            'next_due_date' => $dueDate,
            'anchor_day' => $dueDate->day,
        ];
    }

    public function once(): static
    {
        return $this->state(['frequency' => ScheduleFrequency::Once]);
    }

    public function autoPay(): static
    {
        return $this->state(['auto_pay' => true]);
    }

    public function completed(): static
    {
        return $this->state(['completed_at' => now()]);
    }
}
