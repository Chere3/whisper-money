import { store } from '@/actions/App/Http/Controllers/TransferController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Account, filterTransactionalAccounts } from '@/types/account';
import { todayDateString } from '@/utils/date';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { ArrowDown } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    accounts: Account[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The list behind the dialog keeps its rows in state, so it is told. */
    onCreated?: () => void;
}

/**
 * Money moved between two of the user's own accounts, recorded as a linked
 * pair: it leaves one account and arrives in the other.
 */
export function TransferDialog({
    accounts,
    open,
    onOpenChange,
    onCreated,
}: Props) {
    const transactional = filterTransactionalAccounts(accounts);
    const [fromId, setFromId] = useState('');
    const [toId, setToId] = useState('');
    const [amount, setAmount] = useState(0);
    const [date, setDate] = useState(todayDateString());
    const [description, setDescription] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setFromId('');
            setToId('');
            setAmount(0);
            setDate(todayDateString());
            setDescription('');
            setErrors({});
        }
    }, [open]);

    const from = transactional.find((account) => account.id === fromId);
    // One amount leaves and the same amount arrives, so the destination has to
    // be in the currency of the account the money comes from.
    const destinations = transactional.filter(
        (account) =>
            account.id !== fromId &&
            (!from || account.currency_code === from.currency_code),
    );

    const handleFromChange = (id: string) => {
        setFromId(id);

        const next = transactional.find((account) => account.id === id);
        const to = transactional.find((account) => account.id === toId);

        if (to && (to.id === id || to.currency_code !== next?.currency_code)) {
            setToId('');
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});
        setIsSubmitting(true);

        router.post(
            store().url,
            {
                from_account_id: fromId,
                to_account_id: toId,
                amount,
                transaction_date: date,
                description: description || null,
            },
            {
                onSuccess: () => {
                    onOpenChange(false);
                    onCreated?.();
                },
                onError: (formErrors) =>
                    setErrors(formErrors as Record<string, string>),
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[440px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {__('Transfer between accounts')}
                        </DialogTitle>
                        <DialogDescription>
                            {__(
                                'Linked pair: the amount leaves one account and arrives in the other.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="transfer-from">{__('From')}</Label>
                            <Select
                                value={fromId}
                                onValueChange={handleFromChange}
                            >
                                <SelectTrigger id="transfer-from">
                                    <SelectValue
                                        placeholder={__('Select account')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {transactional.map((account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.from_account_id} />
                        </div>

                        <ArrowDown className="mx-auto size-4 text-muted-foreground" />

                        <div className="space-y-2">
                            <Label htmlFor="transfer-to">{__('To')}</Label>
                            <Select
                                value={toId}
                                onValueChange={setToId}
                                disabled={!from}
                            >
                                <SelectTrigger id="transfer-to">
                                    <SelectValue
                                        placeholder={__('Select account')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {destinations.map((account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {from && destinations.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'No other account in :currency to transfer to.',
                                        { currency: from.currency_code },
                                    )}
                                </p>
                            )}
                            <InputError message={errors.to_account_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="transfer-amount">
                                    {__('Amount')}
                                </Label>
                                <AmountInput
                                    id="transfer-amount"
                                    value={amount}
                                    onChange={(value) =>
                                        setAmount(Math.abs(value))
                                    }
                                    currencyCode={from?.currency_code ?? 'USD'}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="transfer-date">
                                    {__('Date')}
                                </Label>
                                <Input
                                    id="transfer-date"
                                    type="date"
                                    max={todayDateString()}
                                    value={date}
                                    onChange={(e) => setDate(e.target.value)}
                                    required
                                />
                                <InputError message={errors.transaction_date} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="transfer-description">
                                {__('Description (optional)')}
                            </Label>
                            <Input
                                id="transfer-description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                            />
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
                                isSubmitting || !fromId || !toId || amount === 0
                            }
                        >
                            {__('Transfer')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
