<?php

namespace App\Console\Commands;

use App\Models\ScheduledTransaction;
use App\Notifications\ScheduledTransactionDue;
use App\Services\ScheduledTransactionService;
use Illuminate\Console\Command;

class ProcessScheduledTransactions extends Command
{
    protected $signature = 'scheduled-transactions:process';

    protected $description = 'Record the scheduled transactions that pay themselves and remind about the ones waiting to be paid';

    /**
     * How many occurrences one run records for a single schedule. A weekly
     * schedule on a server that was down for a month has four to catch up on;
     * a row with a due date years in the past is a mistake, not a backlog.
     */
    private const MAX_CATCH_UP = 12;

    public function handle(ScheduledTransactionService $scheduledTransactions): int
    {
        $recorded = 0;
        $reminded = 0;

        // Lazily: the run reads every due schedule of every user, and none of
        // them needs to be in memory once it has been handled.
        $due = ScheduledTransaction::query()
            ->pending()
            ->whereDate('next_due_date', '<=', today())
            ->with('user')
            ->lazyById();

        foreach ($due as $scheduled) {
            if ($scheduled->auto_pay) {
                $recorded += $this->recordDueOccurrences($scheduled, $scheduledTransactions);

                continue;
            }

            $reminded += (int) $this->remindOnce($scheduled);
        }

        $this->info("Recorded {$recorded} scheduled transaction(s), sent {$reminded} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Each occurrence is dated the day it fell due, not the day the run got to
     * it, and paying moves the due date along - which is also what makes a
     * second run on the same day find nothing left to do.
     */
    private function recordDueOccurrences(ScheduledTransaction $scheduled, ScheduledTransactionService $scheduledTransactions): int
    {
        $recorded = 0;

        while (! $scheduled->isCompleted() && $scheduled->next_due_date->lte(today()) && $recorded < self::MAX_CATCH_UP) {
            $scheduledTransactions->pay($scheduled, $scheduled->next_due_date);
            $recorded++;
        }

        return $recorded;
    }

    /**
     * One reminder per occurrence: the due date it was sent for is stored, so
     * an overdue row is not announced again every morning.
     */
    private function remindOnce(ScheduledTransaction $scheduled): bool
    {
        if ($scheduled->reminded_for?->equalTo($scheduled->next_due_date)) {
            return false;
        }

        $scheduled->user->notify(new ScheduledTransactionDue($scheduled));
        $scheduled->update(['reminded_for' => $scheduled->next_due_date]);

        return true;
    }
}
