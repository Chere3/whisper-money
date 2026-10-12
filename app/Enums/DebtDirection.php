<?php

namespace App\Enums;

enum DebtDirection: string
{
    /** The user handed money over and is owed it back. */
    case Lent = 'lent';

    /** The user received money and owes it back. */
    case Borrowed = 'borrowed';
}
