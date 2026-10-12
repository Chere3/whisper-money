<?php

namespace App\Notifications;

use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Rings the bell when a scheduled transaction falls due and is waiting to be
 * marked as paid.
 *
 * Database only, and identifiers plus the description as it read that day: the
 * row is worded at read time, and it carries no amount because a figure printed
 * in the bell would sit in the clear through privacy mode.
 */
class ScheduledTransactionDue extends Notification
{
    public function __construct(public ScheduledTransaction $scheduled) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'scheduled_transaction_id' => $this->scheduled->id,
            'space_id' => $this->scheduled->space_id,
            'description' => $this->scheduled->description,
            'due_date' => $this->scheduled->next_due_date->toDateString(),
            'income' => $this->scheduled->amount > 0,
        ];
    }
}
