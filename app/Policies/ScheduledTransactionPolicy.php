<?php

namespace App\Policies;

use App\Models\ScheduledTransaction;
use App\Models\User;

class ScheduledTransactionPolicy
{
    public function update(User $user, ScheduledTransaction $scheduledTransaction): bool
    {
        return $user->id === $scheduledTransaction->user_id;
    }

    /**
     * Paying or skipping moves the due date along, which a schedule that has
     * run out no longer has.
     */
    public function advance(User $user, ScheduledTransaction $scheduledTransaction): bool
    {
        return $this->update($user, $scheduledTransaction) && ! $scheduledTransaction->isCompleted();
    }

    public function delete(User $user, ScheduledTransaction $scheduledTransaction): bool
    {
        return $this->update($user, $scheduledTransaction);
    }
}
