<?php

namespace App\Services;

use App\Enums\CategoryType;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\JoinClause;

/**
 * What the user spent on each day, for the dashboard's spending calendar.
 */
class DailySpendingService
{
    /**
     * Weeks the calendar shows, the current one included.
     */
    private const WEEKS = 13;

    /**
     * Expense spending per day, for every day that had any.
     *
     * Counted the way the other dashboard cards count it: transactions in an
     * expense category, ownership-weighted, without what an archived account
     * no longer contributes, and summed as stored with no currency conversion.
     * A day whose refunds outweigh its spending is left out rather than shown
     * as negative.
     *
     * @return array<string, positive-int> keyed by `Y-m-d`
     */
    public function forPeriod(string $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        return Transaction::query()
            ->where('transactions.user_id', $userId)
            ->whereBetween('transactions.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->joinOwningAccount()
            ->withoutArchivedAccountActivity()
            ->join('categories', function (JoinClause $join): void {
                $join->on('transactions.category_id', '=', 'categories.id')
                    ->where('categories.type', '=', CategoryType::Expense)
                    ->whereNull('categories.deleted_at');
            })
            ->groupBy('transactions.transaction_date')
            ->selectRaw('transactions.transaction_date as day, -sum('.Transaction::OWNED_AMOUNT_SQL.') as spent')
            ->toBase()
            ->get()
            ->filter(fn (object $row): bool => (int) $row->spent > 0)
            ->mapWithKeys(fn (object $row): array => [substr((string) $row->day, 0, 10) => (int) $row->spent])
            ->all();
    }

    /**
     * The calendar's window: whole weeks ending with the one today falls in,
     * so the grid always starts on a Monday.
     *
     * @return array{from: string, to: string, days: array<string, positive-int>}
     */
    public function calendar(string $userId, CarbonInterface $today): array
    {
        $from = $today->copy()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(self::WEEKS - 1);

        return [
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
            'days' => $this->forPeriod($userId, $from, $today),
        ];
    }
}
