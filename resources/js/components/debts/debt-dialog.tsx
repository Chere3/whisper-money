import { store, update } from '@/actions/App/Http/Controllers/DebtController';
import InputError from '@/components/input-error';
import { AmountInput } from '@/components/ui/amount-input';
import { Button } from '@/components/ui/button';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Debt, DebtDirection } from '@/types/debt';
import { todayDateString } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Props {
    /** The debt being edited; leave out to add one. */
    debt?: Debt;
    currencyCode: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface FormState {
    direction: DebtDirection;
    counterparty: string;
    description: string;
    amount: number;
    lentOn: string;
    dueDate: string;
}

function initialState(debt?: Debt): FormState {
    return {
        direction: debt?.direction ?? 'lent',
        counterparty: debt?.counterparty ?? '',
        description: debt?.description ?? '',
        amount: debt?.amount ?? 0,
        lentOn: debt?.lent_on ?? todayDateString(),
        dueDate: debt?.due_date ?? '',
    };
}

export function DebtDialog({ debt, currencyCode, open, onOpenChange }: Props) {
    const [form, setForm] = useState<FormState>(() => initialState(debt));
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setForm(initialState(debt));
            setErrors({});
        }
    }, [open, debt]);

    const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});
        setIsSubmitting(true);

        const route = debt ? update({ debt: debt.id }) : store();

        router.visit(route.url, {
            method: route.method,
            data: {
                direction: form.direction,
                counterparty: form.counterparty,
                description: form.description || null,
                amount: form.amount,
                lent_on: form.lentOn,
                due_date: form.dueDate || null,
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
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[480px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {debt ? __('Edit debt') : __('Add a debt')}
                        </DialogTitle>
                        <DialogDescription>
                            {__(
                                'Keep track of money you lent or borrowed. It is tracked on its own and does not create a transaction.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            className="w-full"
                            value={form.direction}
                            onValueChange={(value) =>
                                value &&
                                set('direction', value as DebtDirection)
                            }
                        >
                            <ToggleGroupItem
                                value="lent"
                                className="flex-1 px-4"
                            >
                                {__('I lent')}
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="borrowed"
                                className="flex-1 px-4"
                            >
                                {__('I borrowed')}
                            </ToggleGroupItem>
                        </ToggleGroup>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="debt-counterparty">
                                    {form.direction === 'lent'
                                        ? __('To whom')
                                        : __('From whom')}
                                </Label>
                                <Input
                                    id="debt-counterparty"
                                    value={form.counterparty}
                                    onChange={(e) =>
                                        set('counterparty', e.target.value)
                                    }
                                    placeholder={__('e.g., Ana')}
                                    required
                                />
                                <InputError message={errors.counterparty} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="debt-amount">
                                    {__('Amount')}
                                </Label>
                                <AmountInput
                                    id="debt-amount"
                                    value={form.amount}
                                    onChange={(amount) =>
                                        set('amount', Math.abs(amount))
                                    }
                                    currencyCode={currencyCode}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="debt-description">
                                {__('What for (optional)')}
                            </Label>
                            <Input
                                id="debt-description"
                                value={form.description}
                                onChange={(e) =>
                                    set('description', e.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="debt-lent-on">
                                    {__('Date')}
                                </Label>
                                <Input
                                    id="debt-lent-on"
                                    type="date"
                                    value={form.lentOn}
                                    onChange={(e) =>
                                        set('lentOn', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.lent_on} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="debt-due-date">
                                    {__('Due back (optional)')}
                                </Label>
                                <Input
                                    id="debt-due-date"
                                    type="date"
                                    min={form.lentOn}
                                    value={form.dueDate}
                                    onChange={(e) =>
                                        set('dueDate', e.target.value)
                                    }
                                />
                                <InputError message={errors.due_date} />
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
                                !form.counterparty ||
                                form.amount === 0
                            }
                        >
                            {debt ? __('Save changes') : __('Add debt')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
