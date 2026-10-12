<?php

use App\Enums\ScheduleFrequency;
use Carbon\CarbonImmutable;

test('each frequency steps to its next due date', function (ScheduleFrequency $frequency, string $from, ?string $expected) {
    $anchorDay = CarbonImmutable::parse($from)->day;

    expect($frequency->following(CarbonImmutable::parse($from), $anchorDay)?->toDateString())->toBe($expected);
})->with([
    'once has nothing after it' => [ScheduleFrequency::Once, '2026-03-10', null],
    'weekly' => [ScheduleFrequency::Weekly, '2026-03-10', '2026-03-17'],
    'biweekly' => [ScheduleFrequency::Biweekly, '2026-03-10', '2026-03-24'],
    'monthly' => [ScheduleFrequency::Monthly, '2026-03-10', '2026-04-10'],
    'monthly across a year end' => [ScheduleFrequency::Monthly, '2026-12-15', '2027-01-15'],
    'yearly' => [ScheduleFrequency::Yearly, '2026-03-10', '2027-03-10'],
]);

test('a monthly schedule set on the 31st clamps to short months and comes back', function () {
    $date = CarbonImmutable::parse('2026-01-31');
    $dates = [];

    foreach (range(1, 4) as $_) {
        $date = ScheduleFrequency::Monthly->following($date, 31);
        $dates[] = $date->toDateString();
    }

    expect($dates)->toBe(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31']);
});

test('a yearly schedule set on 29 February falls on the 28th outside leap years', function () {
    $first = ScheduleFrequency::Yearly->following(CarbonImmutable::parse('2028-02-29'), 29);
    $date = $first;

    foreach (range(1, 3) as $_) {
        $date = ScheduleFrequency::Yearly->following($date, 29);
    }

    expect($first->toDateString())->toBe('2029-02-28')
        ->and($date->toDateString())->toBe('2032-02-29');
});
