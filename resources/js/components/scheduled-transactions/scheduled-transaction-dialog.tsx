import {
    store,
    update,
} from '@/actions/App/Http/Controllers/ScheduledTransactionController';
import InputError from '@/components/input-error';
import { CategorySelect } from '@/components/transactions/category-select';
import { AmountInput } from '@/components/ui/amount-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Account, filterTransactionalAccounts } from '@/types/account';
import { Category } from '@/types/category';
import {
    getScheduleFrequencyLabel,
    SCHEDULE_FREQUENCIES,
    ScheduledTransaction,
    ScheduleFrequency,
} from '@/types/scheduled-transaction';
import { todayDateString } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Props {
    /** The schedule being edited; leave out to create one. */
    scheduledTransaction?: ScheduledTransaction;
    accounts: Account[];
    categories: Category[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface FormState {
    description: string;
    type: 'expense' | 'income';
    unsignedAmount: number;
    accountId: string;
    categoryId: string | null;
    frequency: ScheduleFrequency;
    nextDueDate: string;
    endDate: string;
    notes: string;
    autoPay: boolean;
}

function initialState(
    scheduled: ScheduledTransaction | undefined,
    accounts: Account[],
): FormState {
    return {
        description: scheduled?.description ?? '',
        type: scheduled && scheduled.amount > 0 ? 'income' : 'expense',
        unsignedAmount: Math.abs(scheduled?.amount ?? 0),
        accountId: scheduled?.account_id ?? accounts[0]?.id ?? '',
        categoryId: scheduled?.category_id ?? null,
        frequency: scheduled?.frequency ?? 'monthly',
        nextDueDate: scheduled?.next_due_date ?? todayDateString(),
        endDate: scheduled?.end_date ?? '',
        notes: scheduled?.notes ?? '',
        autoPay: scheduled?.auto_pay ?? false,
    };
}

export function ScheduledTransactionDialog({
    scheduledTransaction,
    accounts,
    categories,
    open,
    onOpenChange,
}: Props) {
    const transactionalAccounts = filterTransactionalAccounts(accounts);
    const [form, setForm] = useState<FormState>(() =>
        initialState(scheduledTransaction, transactionalAccounts),
    );
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setForm(
                initialState(
                    scheduledTransaction,
                    filterTransactionalAccounts(accounts),
                ),
            );
            setErrors({});
        }
    }, [open, scheduledTransaction, accounts]);

    const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const currencyCode =
        transactionalAccounts.find((account) => account.id === form.accountId)
            ?.currency_code ?? 'USD';
    const repeats = form.frequency !== 'once';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});
        setIsSubmitting(true);

        const route = scheduledTransaction
            ? update({ scheduledTransaction: scheduledTransaction.id })
            : store();

        router.visit(route.url, {
            method: route.method,
            data: {
                account_id: form.accountId,
                category_id: form.categoryId,
                description: form.description,
                amount:
                    form.type === 'income'
                        ? form.unsignedAmount
                        : -form.unsignedAmount,
                notes: form.notes || null,
                frequency: form.frequency,
                next_due_date: form.nextDueDate,
                end_date: repeats && form.endDate ? form.endDate : null,
                auto_pay: form.autoPay,
            },
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (formErrors) =>
                setErrors(formErrors as Record<string, string>),
            onFinish: () => setIsSubmitting(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[520px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {scheduledTransaction
                                ? __('Edit scheduled transaction')
                                : __('Schedule a transaction')}
                        </DialogTitle>
                        <DialogDescription>
                            {__(
                                'A bill, a subscription or an income you expect. It only becomes a transaction when you mark it as paid.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="scheduled-description">
                                {__('Description')}
                            </Label>
                            <Input
                                id="scheduled-description"
                                value={form.description}
                                onChange={(e) =>
                                    set('description', e.target.value)
                                }
                                placeholder={__('e.g., Rent')}
                                required
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="scheduled-amount">
                                {__('Amount')}
                            </Label>
                            <div className="flex flex-col items-stretch gap-3 sm:flex-row">
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    className="w-full sm:w-fit"
                                    value={form.type}
                                    onValueChange={(value) =>
                                        value &&
                                        set('type', value as FormState['type'])
                                    }
                                >
                                    <ToggleGroupItem
                                        value="expense"
                                        className="flex-1 px-4"
                                    >
                                        {__('Expense')}
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="income"
                                        className="flex-1 px-4"
                                    >
                                        {__('Income')}
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                <div className="flex-1">
                                    <AmountInput
                                        id="scheduled-amount"
                                        value={form.unsignedAmount}
                                        // The toggle owns the sign.
                                        onChange={(amount) =>
                                            set(
                                                'unsignedAmount',
                                                Math.abs(amount),
                                            )
                                        }
                                        currencyCode={currencyCode}
                                        required
                                    />
                                </div>
                            </div>
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="scheduled-account">
                                    {__('Account')}
                                </Label>
                                <Select
                                    value={form.accountId}
                                    onValueChange={(value) =>
                                        set('accountId', value)
                                    }
                                >
                                    <SelectTrigger id="scheduled-account">
                                        <SelectValue
                                            placeholder={__('Select account')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {transactionalAccounts.map(
                                            (account) => (
                                                <SelectItem
                                                    key={account.id}
                                                    value={account.id}
                                                >
                                                    {account.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.account_id} />
                            </div>

                            <div className="space-y-2">
                                <Label>{__('Category')}</Label>
                                <CategorySelect
                                    value={form.categoryId}
                                    onValueChange={(value) =>
                                        set('categoryId', value || null)
                                    }
                                    categories={categories}
                                    triggerClassName="w-full"
                                />
                                <InputError message={errors.category_id} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="scheduled-frequency">
                                    {__('Repeats')}
                                </Label>
                                <Select
                                    value={form.frequency}
                                    onValueChange={(value) =>
                                        set(
                                            'frequency',
                                            value as ScheduleFrequency,
                                        )
                                    }
                                >
                                    <SelectTrigger id="scheduled-frequency">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {SCHEDULE_FREQUENCIES.map(
                                            (frequency) => (
                                                <SelectItem
                                                    key={frequency}
                                                    value={frequency}
                                                >
                                                    {getScheduleFrequencyLabel(
                                                        frequency,
                                                    )}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.frequency} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="scheduled-next-due-date">
                                    {repeats
                                        ? __('Next due date')
                                        : __('Due date')}
                                </Label>
                                <Input
                                    id="scheduled-next-due-date"
                                    type="date"
                                    value={form.nextDueDate}
                                    onChange={(e) =>
                                        set('nextDueDate', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.next_due_date} />
                            </div>
                        </div>

                        {repeats && (
                            <div className="space-y-2">
                                <Label htmlFor="scheduled-end-date">
                                    {__('End date (optional)')}
                                </Label>
                                <Input
                                    id="scheduled-end-date"
                                    type="date"
                                    min={form.nextDueDate}
                                    value={form.endDate}
                                    onChange={(e) =>
                                        set('endDate', e.target.value)
                                    }
                                />
                                <InputError message={errors.end_date} />
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="scheduled-notes">
                                {__('Notes')}
                            </Label>
                            <Input
                                id="scheduled-notes"
                                value={form.notes}
                                onChange={(e) => set('notes', e.target.value)}
                            />
                        </div>

                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="scheduled-auto-pay"
                                className="mt-0.5"
                                checked={form.autoPay}
                                onCheckedChange={(checked) =>
                                    set('autoPay', checked === true)
                                }
                            />
                            <div className="space-y-1">
                                <Label htmlFor="scheduled-auto-pay">
                                    {__('Record it automatically')}
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'The transaction is created on the due date. Leave it off to get a reminder and mark it as paid yourself.',
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {__('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                isSubmitting ||
                                !form.description ||
                                !form.accountId ||
                                form.unsignedAmount === 0
                            }
                        >
                            {scheduledTransaction
                                ? __('Save changes')
                                : __('Schedule')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
