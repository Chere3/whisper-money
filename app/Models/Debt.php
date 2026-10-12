<?php

namespace App\Models;

use App\Enums\DebtDirection;
use App\Models\Concerns\BelongsToSpace;
use Carbon\CarbonInterface;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money owed between the user and another person. It is a ledger of its own:
 * no transaction is created for it, so it never touches budgets or balances.
 *
 * @property string $id
 * @property string $user_id
 * @property ?string $space_id
 * @property DebtDirection $direction
 * @property string $counterparty
 * @property ?string $description
 * @property int $amount
 * @property int $paid_amount
 * @property string $currency_code
 * @property CarbonInterface $lent_on
 * @property ?CarbonInterface $due_date
 * @property ?CarbonInterface $settled_at
 */
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use BelongsToSpace, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'space_id',
        'direction',
        'counterparty',
        'description',
        'amount',
        'paid_amount',
        'currency_code',
        'lent_on',
        'due_date',
        'settled_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'space_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => DebtDirection::class,
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'lent_on' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    public function remainingAmount(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    /**
     * Count a payment towards the debt. One that covers what is left settles
     * it, and never counts for more than was owed.
     */
    public function recordPayment(int $amount): void
    {
        $paid = min($this->amount, $this->paid_amount + $amount);

        $this->update([
            'paid_amount' => $paid,
            'settled_at' => $paid >= $this->amount ? now() : null,
        ]);
    }
}
