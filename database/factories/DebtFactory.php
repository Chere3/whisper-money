<?php

namespace Database\Factories;

use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'direction' => DebtDirection::Lent,
            'counterparty' => fake()->firstName(),
            'description' => null,
            'amount' => fake()->numberBetween(1000, 50000),
            'paid_amount' => 0,
            'currency_code' => 'USD',
            'lent_on' => today(),
            'due_date' => null,
        ];
    }

    public function borrowed(): static
    {
        return $this->state(['direction' => DebtDirection::Borrowed]);
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['amount'],
            'settled_at' => now(),
        ]);
    }
}
