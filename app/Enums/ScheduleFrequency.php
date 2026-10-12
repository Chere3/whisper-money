<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum ScheduleFrequency: string
{
    case Once = 'once';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * The due date that follows the given one, or null when nothing follows.
     *
     * `$anchorDay` is the day of the month the schedule was set up on. Months
     * are stepped without overflow and then pulled back to it, so a bill due on
     * the 31st falls on 28 February and returns to 31 March, instead of
     * drifting to the 28th for good.
     */
    public function following(CarbonInterface $date, int $anchorDay): ?CarbonImmutable
    {
        $date = CarbonImmutable::instance($date)->startOfDay();

        return match ($this) {
            self::Once => null,
            self::Weekly => $date->addWeek(),
            self::Biweekly => $date->addWeeks(2),
            self::Monthly => self::onAnchorDay($date->addMonthNoOverflow(), $anchorDay),
            self::Yearly => self::onAnchorDay($date->addYearNoOverflow(), $anchorDay),
        };
    }

    private static function onAnchorDay(CarbonImmutable $date, int $anchorDay): CarbonImmutable
    {
        return $date->day(min($anchorDay, $date->daysInMonth));
    }
}
