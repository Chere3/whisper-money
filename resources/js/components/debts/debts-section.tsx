import { destroy } from '@/actions/App/Http/Controllers/DebtController';
import { DebtDialog } from '@/components/debts/debt-dialog';
import { DebtPaymentDialog } from '@/components/debts/debt-payment-dialog';
import HeadingSmall from '@/components/heading-small';
import { ConfirmDeleteDialog } from '@/components/shared/confirm-delete-dialog';
import { RowMenu } from '@/components/shared/row-menu';
import { AmountDisplay } from '@/components/ui/amount-display';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { useLocale } from '@/hooks/use-locale';
import { Debt, debtRemaining, debtTotals } from '@/types/debt';
import { formatDate, todayDateString } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { HandCoins, Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    debts: Debt[];
    currencyCode: string;
}

/** Which dialog is open, and for which debt (none when adding). */
type OpenDialog =
    | { kind: 'form'; debt?: Debt }
    | { kind: 'payment'; debt: Debt }
    | { kind: 'delete'; debt: Debt }
    | null;

/**
 * Money lent and borrowed, person by person: what is still open on top, what
 * was settled below it.
 */
export function DebtsSection({ debts, currencyCode }: Props) {
    const [dialog, setDialog] = useState<OpenDialog>(null);
    const open = debts.filter((debt) => debt.settled_at === null);
    const settled = debts.filter((debt) => debt.settled_at !== null);
    const totals = debtTotals(open);

    const handleDelete = (debt: Debt) => {
        router.delete(destroy({ debt: debt.id }).url, {
            preserveScroll: true,
            onFinish: () => setDialog(null),
        });
    };

    const row = (debt: Debt) => (
        <DebtRow
            key={debt.id}
            debt={debt}
            onPay={() => setDialog({ kind: 'payment', debt })}
            onEdit={() => setDialog({ kind: 'form', debt })}
            onDelete={() => setDialog({ kind: 'delete', debt })}
        />
    );

    return (
        <section className="space-y-4" data-testid="debts-section">
            <div className="flex items-center justify-between gap-2">
                <HeadingSmall
                    title={__('Lent & borrowed')}
                    description={__(
                        'Money you are owed and money you owe, person by person',
                    )}
                />
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setDialog({ kind: 'form' })}
                >
                    <Plus />
                    {__('Add debt')}
                </Button>
            </div>

            {debts.length === 0 ? (
                <div className="flex items-center gap-3 rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
                    <HandCoins className="size-5 shrink-0" />
                    {__(
                        'Nothing lent or borrowed. Add a debt to remember who owes what.',
                    )}
                </div>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TotalCard
                            label={__('You are owed')}
                            amount={totals.lent}
                            currencyCode={currencyCode}
                            testId="debts-total-lent"
                        />
                        <TotalCard
                            label={__('You owe')}
                            amount={totals.borrowed}
                            currencyCode={currencyCode}
                            testId="debts-total-borrowed"
                        />
                    </div>

                    {open.length > 0 && (
                        <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                            {open.map(row)}
                        </ul>
                    )}

                    {settled.length > 0 && (
                        <details className="space-y-2">
                            <summary className="cursor-pointer text-sm font-medium text-muted-foreground">
                                {__('Settled (:count)', {
                                    count: settled.length,
                                })}
                            </summary>
                            <ul className="divide-y divide-border rounded-lg border border-border bg-card opacity-70">
                                {settled.map(row)}
                            </ul>
                        </details>
                    )}
                </>
            )}

            <DebtDialog
                debt={dialog?.kind === 'form' ? dialog.debt : undefined}
                currencyCode={currencyCode}
                open={dialog?.kind === 'form'}
                onOpenChange={(isOpen) => !isOpen && setDialog(null)}
            />

            <DebtPaymentDialog
                debt={dialog?.kind === 'payment' ? dialog.debt : null}
                onOpenChange={(isOpen) => !isOpen && setDialog(null)}
            />

            <ConfirmDeleteDialog
                title={__('Delete debt')}
                description={__(
                    'The debt and the payments recorded on it will be removed.',
                )}
                open={dialog?.kind === 'delete'}
                onOpenChange={(isOpen) => !isOpen && setDialog(null)}
                onConfirm={() =>
                    dialog?.kind === 'delete' && handleDelete(dialog.debt)
                }
            />
        </section>
    );
}

function TotalCard({
    label,
    amount,
    currencyCode,
    testId,
}: {
    label: string;
    amount: number;
    currencyCode: string;
    testId: string;
}) {
    return (
        <div
            className="rounded-lg border border-border bg-card p-4"
            data-testid={testId}
        >
            <div className="text-sm text-muted-foreground">{label}</div>
            <AmountDisplay
                amountInCents={amount}
                currencyCode={currencyCode}
                className="text-xl font-semibold tabular-nums"
            />
        </div>
    );
}

interface RowProps {
    debt: Debt;
    onPay: () => void;
    onEdit: () => void;
    onDelete: () => void;
}

function DebtRow({ debt, onPay, onEdit, onDelete }: RowProps) {
    const locale = useLocale();
    const settled = debt.settled_at !== null;
    const overdue =
        !settled && debt.due_date !== null && debt.due_date < todayDateString();
    const percentagePaid = (debt.paid_amount / debt.amount) * 100;

    return (
        <li
            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            data-testid={`debt-${debt.id}`}
        >
            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-medium">
                        {debt.counterparty}
                    </span>
                    <Badge variant="secondary">
                        {debt.direction === 'lent'
                            ? __('Owes you')
                            : __('You owe')}
                    </Badge>
                    {overdue && (
                        <Badge className="bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            {__('Overdue')}
                        </Badge>
                    )}
                </div>
                <div className="text-sm text-muted-foreground">
                    {[
                        debt.description,
                        formatDate(debt.lent_on, 'MMM d, yyyy', locale),
                        debt.due_date &&
                            __('due :date', {
                                date: formatDate(
                                    debt.due_date,
                                    'MMM d, yyyy',
                                    locale,
                                ),
                            }),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </div>
                {debt.paid_amount > 0 && !settled && (
                    <Progress value={percentagePaid} className="h-1.5" />
                )}
            </div>

            <div className="flex items-center justify-between gap-2 sm:justify-end">
                <div className="mr-2 text-right">
                    <AmountDisplay
                        amountInCents={
                            settled ? debt.amount : debtRemaining(debt)
                        }
                        currencyCode={debt.currency_code}
                        className="font-semibold tabular-nums"
                    />
                    {debt.paid_amount > 0 && !settled && (
                        <div className="text-xs text-muted-foreground">
                            {__('of')}{' '}
                            <AmountDisplay
                                amountInCents={debt.amount}
                                currencyCode={debt.currency_code}
                            />
                        </div>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    {!settled && (
                        <Button size="sm" onClick={onPay}>
                            {__('Record payment')}
                        </Button>
                    )}
                    <RowMenu onEdit={onEdit} onDelete={onDelete} />
                </div>
            </div>
        </li>
    );
}
