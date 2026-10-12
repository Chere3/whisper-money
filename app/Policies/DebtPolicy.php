<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;

class DebtPolicy
{
    public function update(User $user, Debt $debt): bool
    {
        return $user->id === $debt->user_id;
    }

    /**
     * A settled debt has nothing left to pay.
     */
    public function recordPayment(User $user, Debt $debt): bool
    {
        return $this->update($user, $debt) && ! $debt->isSettled();
    }

    public function delete(User $user, Debt $debt): bool
    {
        return $this->update($user, $debt);
    }
}
