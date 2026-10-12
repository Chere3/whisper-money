import { index as transactionsIndex } from '@/actions/App/Http/Controllers/TransactionController';
import { PrivacyRevealButton } from '@/components/privacy-reveal-button';
import { AmountDisplay } from '@/components/ui/amount-display';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
import { formatDate, formatLocalDate, toLocalDate } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { Link, usePage } from '@inertiajs/react';

export interface SpendingCalendar {
    /** The Monday the grid starts on, `YYYY-MM-DD`. */
    from: string;
    /** Today, `YYYY-MM-DD`. */
    to: string;
    /** What was spent on each day that had any, in minor units. */
    days: Record<string, number>;
}

/** Shades from "nothing spent" to "the most expensive days". */
const LEVEL_CLASS = [
    'bg-muted',
    'bg-emerald-200 dark:bg-emerald-900',
    'bg-emerald-400 dark:bg-emerald-700',
    'bg-emerald-500 dark:bg-emerald-500',
    'bg-emerald-700 dark:bg-emerald-300',
] as const;

/**
 * Which shade a day gets: none for a day without spending, then four even
 * steps up to the most expensive day on the calendar.
 */
export function spendingLevel(amount: number, max: number): number {
    if (amount <= 0 || max <= 0) {
        return 0;
    }

    return Math.min(4, Math.ceil((amount / max) * 4));
}

/**
 * Every day from the first Monday to today, in columns of one week each. The
 * current week stops at today instead of padding itself with days to come.
 */
export function calendarWeeks(from: string, to: string): string[][] {
    const weeks: string[][] = [];
    const cursor = toLocalDate(from);
    const last = toLocalDate(to);

    while (cursor <= last) {
        if (weeks.length === 0 || weeks[weeks.length - 1].length === 7) {
            weeks.push([]);
        }

        weeks[weeks.length - 1].push(formatLocalDate(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }

    return weeks;
}

/**
 * Daily spending over the last weeks as a grid of days, darker where more was
 * spent, so a costly weekend or a quiet month reads at a glance. Each day
 * links to its transactions.
 */
export function SpendingCalendarCard({
    calendar,
}: {
    calendar: SpendingCalendar | null;
}) {
    const { auth } = usePage<SharedData>().props;
    const locale = useLocale();

    if (!calendar || !auth?.user) {
        return null;
    }

    const amounts = Object.values(calendar.days);
    const weeks = calendarWeeks(calendar.from, calendar.to);

    // Nothing spent in the whole window: an empty grid would take a dashboard
    // slot to say nothing.
    if (amounts.length === 0) {
        return null;
    }

    const max = Math.max(...amounts);
    const total = amounts.reduce((sum, amount) => sum + amount, 0);
    const [priciestDay] = Object.entries(calendar.days).sort(
        ([, a], [, b]) => b - a,
    )[0];
    const currencyCode = auth.user.currency_code;

    return (
        <Card className="w-full" data-testid="spending-calendar">
            <CardHeader className="gap-2">
                <div className="flex items-start justify-between gap-2">
                    <CardTitle>{__('Spending calendar')}</CardTitle>
                    <PrivacyRevealButton className="-my-2" />
                </div>
                <CardDescription>
                    {__('on the last :count weeks', { count: weeks.length })}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div
                    className="grid grid-flow-col grid-rows-7 justify-start gap-1 overflow-x-auto"
                    role="grid"
                    aria-label={__('Spending calendar')}
                >
                    {weeks.flat().map((day) => (
                        <Link
                            key={day}
                            href={
                                transactionsIndex({
                                    query: { date_from: day, date_to: day },
                                }).url
                            }
                            // The date only: an amount in a tooltip would sit
                            // in the clear through privacy mode.
                            title={formatDate(day, 'MMM d, yyyy', locale)}
                            aria-label={formatDate(day, 'MMM d, yyyy', locale)}
                            data-level={spendingLevel(
                                calendar.days[day] ?? 0,
                                max,
                            )}
                            className={cn(
                                'size-4 rounded-sm transition-opacity hover:opacity-70 sm:size-5',
                                LEVEL_CLASS[
                                    spendingLevel(calendar.days[day] ?? 0, max)
                                ],
                            )}
                        />
                    ))}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-sm">
                    <dl className="flex flex-wrap gap-x-6 gap-y-2">
                        <div>
                            <dt className="text-muted-foreground">
                                {__('Total spent')}
                            </dt>
                            <dd className="font-medium">
                                <AmountDisplay
                                    amountInCents={total}
                                    currencyCode={currencyCode}
                                />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                {__('Most expensive day')}
                            </dt>
                            <dd className="font-medium">
                                {formatDate(priciestDay, 'MMM d', locale)}
                                {' · '}
                                <AmountDisplay
                                    amountInCents={max}
                                    currencyCode={currencyCode}
                                />
                            </dd>
                        </div>
                    </dl>

                    <div
                        className="flex items-center gap-1 text-xs text-muted-foreground"
                        aria-hidden
                    >
                        {__('Less')}
                        {LEVEL_CLASS.map((levelClass) => (
                            <span
                                key={levelClass}
                                className={cn('size-3 rounded-sm', levelClass)}
                            />
                        ))}
                        {__('More')}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
