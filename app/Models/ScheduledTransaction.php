<?php

namespace App\Models;

use App\Enums\ScheduleFrequency;
use App\Models\Concerns\BelongsToSpace;
use Carbon\CarbonInterface;
use Database\Factories\ScheduledTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money the user expects to move on a date, once or on a schedule. Paying one
 * creates the real transaction and moves the due date along.
 *
 * @property string $id
 * @property string $user_id
 * @property ?string $space_id
 * @property string $account_id
 * @property ?string $category_id
 * @property string $description
 * @property int $amount
 * @property string $currency_code
 * @property ?string $notes
 * @property ScheduleFrequency $frequency
 * @property CarbonInterface $next_due_date
 * @property int $anchor_day
 * @property ?CarbonInterface $end_date
 * @property ?CarbonInterface $completed_at
 * @property bool $auto_pay
 * @property ?CarbonInterface $reminded_for
 */
class ScheduledTransaction extends Model
{
    /** @use HasFactory<ScheduledTransactionFactory> */
    use BelongsToSpace, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'space_id',
        'account_id',
        'category_id',
        'description',
        'amount',
        'currency_code',
        'notes',
        'frequency',
        'next_due_date',
        'anchor_day',
        'end_date',
        'completed_at',
        'auto_pay',
        'reminded_for',
    ];

    /** @var list<string> */
    protected $hidden = [
        'space_id',
        'anchor_day',
        'reminded_for',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'anchor_day' => 'integer',
            'frequency' => ScheduleFrequency::class,
            'next_due_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'auto_pay' => 'boolean',
            'reminded_for' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Schedules with an occurrence still to come.
     *
     * @param  Builder<ScheduledTransaction>  $query
     * @return Builder<ScheduledTransaction>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
