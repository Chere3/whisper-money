import {
    destroy,
    pay,
    skip,
} from '@/actions/App/Http/Controllers/ScheduledTransactionController';
import HeadingSmall from '@/components/heading-small';
import { ScheduledTransactionDialog } from '@/components/scheduled-transactions/scheduled-transaction-dialog';
import { CategoryBadge } from '@/components/shared/category-combobox';
import { ConfirmDeleteDialog } from '@/components/shared/confirm-delete-dialog';
import { RowMenu } from '@/components/shared/row-menu';
import { AmountDisplay } from '@/components/ui/amount-display';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { Account, filterTransactionalAccounts } from '@/types/account';
import { Category } from '@/types/category';
import {
    dueStatus,
    DueStatus,
    getScheduleFrequencyLabel,
    ScheduledTransaction,
} from '@/types/scheduled-transaction';
import { formatDate } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { CalendarClock, Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    scheduledTransactions: ScheduledTransaction[];
    accounts: Account[];
    categories: Category[];
}

/** Which dialog is open, and for which schedule (none when creating). */
type OpenDialog =
    | { kind: 'form'; scheduled?: ScheduledTransaction }
    | { kind: 'delete'; scheduled: ScheduledTransaction }
    | null;

/**
 * The bills, subscriptions and incomes still to come, soonest first. Paying a
 * row records the real transaction; skipping it only moves the due date.
 */
export function UpcomingTransactions({
    scheduledTransactions,
    accounts,
    categories,
}: Props) {
    const [dialog, setDialog] = useState<OpenDialog>(null);
    const [busyId, setBusyId] = useState<string | null>(null);
    const canSchedule = filterTransactionalAccounts(accounts).length > 0;

    const advance = (
        action: typeof pay | typeof skip,
        scheduled: ScheduledTransaction,
    ) => {
        setBusyId(scheduled.id);
        router.post(
            action({ scheduledTransaction: scheduled.id }).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            },
        );
    };

    const handleDelete = (scheduled: ScheduledTransaction) => {
        router.delete(destroy({ scheduledTransaction: scheduled.id }).url, {
            preserveScroll: true,
            onFinish: () => setDialog(null),
        });
    };

    return (
        <section className="space-y-4" data-testid="upcoming-transactions">
            <div className="flex items-center justify-between gap-2">
                <HeadingSmall
                    title={__('Upcoming')}
                    description={__(
                        'Bills, subscriptions and income you are expecting',
                    )}
                />
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!canSchedule}
                    onClick={() => setDialog({ kind: 'form' })}
                >
                    <Plus />
                    {__('Schedule')}
                </Button>
            </div>

            {scheduledTransactions.length === 0 ? (
                <div className="flex items-center gap-3 rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
                    <CalendarClock className="size-5 shrink-0" />
                    {canSchedule
                        ? __(
                              'Nothing scheduled yet. Add your rent, subscriptions or salary to see what is coming.',
                          )
                        : __(
                              'Add an account that holds transactions to schedule one.',
                          )}
                </div>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                    {scheduledTransactions.map((scheduled) => (
                        <ScheduledRow
                            key={scheduled.id}
                            scheduled={scheduled}
                            busy={busyId === scheduled.id}
                            onPay={() => advance(pay, scheduled)}
                            onSkip={() => advance(skip, scheduled)}
                            onEdit={() =>
                                setDialog({ kind: 'form', scheduled })
                            }
                            onDelete={() =>
                                setDialog({ kind: 'delete', scheduled })
                            }
                        />
                    ))}
                </ul>
            )}

            <ScheduledTransactionDialog
                scheduledTransaction={
                    dialog?.kind === 'form' ? dialog.scheduled : undefined
                }
                accounts={accounts}
                categories={categories}
                open={dialog?.kind === 'form'}
                onOpenChange={(open) => !open && setDialog(null)}
            />

            <ConfirmDeleteDialog
                title={__('Delete scheduled transaction')}
                description={__(
                    'It will stop showing up as upcoming. Transactions you already recorded from it stay where they are.',
                )}
                open={dialog?.kind === 'delete'}
                onOpenChange={(open) => !open && setDialog(null)}
                onConfirm={() =>
                    dialog?.kind === 'delete' && handleDelete(dialog.scheduled)
                }
            />
        </section>
    );
}

const DUE_BADGE_CLASS: Record<DueStatus, string> = {
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    today: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    soon: 'bg-secondary text-secondary-foreground',
    later: 'bg-secondary text-secondary-foreground',
};

interface RowProps {
    scheduled: ScheduledTransaction;
    busy: boolean;
    onPay: () => void;
    onSkip: () => void;
    onEdit: () => void;
    onDelete: () => void;
}

function ScheduledRow({
    scheduled,
    busy,
    onPay,
    onSkip,
    onEdit,
    onDelete,
}: RowProps) {
    const locale = useLocale();
    const status = dueStatus(scheduled.next_due_date);
    const dueDate = formatDate(scheduled.next_due_date, 'MMM d, yyyy', locale);

    const dueLabel: Record<DueStatus, string> = {
        overdue: __('Overdue · :date', { date: dueDate }),
        today: __('Due today'),
        soon: dueDate,
        later: dueDate,
    };

    return (
        <li
            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            data-testid={`scheduled-transaction-${scheduled.id}`}
        >
            <div className="min-w-0 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-medium">
                        {scheduled.description}
                    </span>
                    {scheduled.category && (
                        <CategoryBadge category={scheduled.category} />
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge className={DUE_BADGE_CLASS[status]}>
                        {dueLabel[status]}
                    </Badge>
                    <span>
                        {getScheduleFrequencyLabel(scheduled.frequency)}
                        {scheduled.account
                            ? ` · ${scheduled.account.name}`
                            : ''}
                        {scheduled.auto_pay ? ` · ${__('Automatic')}` : ''}
                    </span>
                </div>
            </div>

            <div className="flex items-center justify-between gap-2 sm:justify-end">
                <AmountDisplay
                    amountInCents={scheduled.amount}
                    currencyCode={scheduled.currency_code}
                    className="mr-2 font-semibold tabular-nums"
                    highlightPositive
                />
                <div className="flex items-center gap-1">
                    <Button size="sm" disabled={busy} onClick={onPay}>
                        {scheduled.amount > 0
                            ? __('Mark as received')
                            : __('Mark as paid')}
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        disabled={busy}
                        onClick={onSkip}
                    >
                        {__('Skip')}
                    </Button>
                    <RowMenu onEdit={onEdit} onDelete={onDelete} />
                </div>
            </div>
        </li>
    );
}
