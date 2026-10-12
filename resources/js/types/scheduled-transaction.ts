import { todayDateString, toLocalDate } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { Account } from './account';
import { Category } from './category';
import { UUID } from './uuid';

export const SCHEDULE_FREQUENCIES = [
    'once',
    'weekly',
    'biweekly',
    'monthly',
    'yearly',
] as const;

export type ScheduleFrequency = (typeof SCHEDULE_FREQUENCIES)[number];

/**
 * Money the user expects to move on a date. It becomes a transaction only when
 * it is paid; until then it counts nowhere.
 */
export interface ScheduledTransaction {
    id: UUID;
    account_id: UUID;
    category_id: UUID | null;
    description: string;
    /** Signed, in the minor units of `currency_code`: negative is an expense. */
    amount: number;
    currency_code: string;
    notes: string | null;
    frequency: ScheduleFrequency;
    next_due_date: string;
    end_date: string | null;
    account?: Account;
    category?: Category | null;
}

export function getScheduleFrequencyLabel(
    frequency: ScheduleFrequency,
): string {
    const labels: Record<ScheduleFrequency, string> = {
        once: __('One time'),
        weekly: __('Weekly'),
        biweekly: __('Bi-weekly'),
        monthly: __('Monthly'),
        yearly: __('Yearly'),
    };

    return labels[frequency];
}

export type DueStatus = 'overdue' | 'today' | 'soon' | 'later';

/** Days within which something due counts as coming up soon. */
const SOON_DAYS = 7;

/**
 * Where a due date sits against today, both as `YYYY-MM-DD`. The row's badge
 * reads it, so "overdue" means the same thing wherever it is shown.
 */
export function dueStatus(
    nextDueDate: string,
    today: string = todayDateString(),
): DueStatus {
    if (nextDueDate < today) {
        return 'overdue';
    }

    if (nextDueDate === today) {
        return 'today';
    }

    const days =
        (toLocalDate(nextDueDate).getTime() - toLocalDate(today).getTime()) /
        86_400_000;

    return days <= SOON_DAYS ? 'soon' : 'later';
}
