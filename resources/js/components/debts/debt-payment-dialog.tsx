import { recordPayment } from '@/actions/App/Http/Controllers/DebtController';
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
import { Label } from '@/components/ui/label';
import { Debt, debtRemaining } from '@/types/debt';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Props {
    debt: Debt | null;
    onOpenChange: (open: boolean) => void;
}

/**
 * A payment towards a debt. It opens on everything that is left, so settling
 * is one click and a partial payment is one edit away.
 */
export function DebtPaymentDialog({ debt, onOpenChange }: Props) {
    const [amount, setAmount] = useState(0);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const remaining = debt ? debtRemaining(debt) : 0;

    useEffect(() => {
        setAmount(remaining);
    }, [debt, remaining]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!debt) {
            return;
        }

        setIsSubmitting(true);
        router.post(
            recordPayment({ debt: debt.id }).url,
            { amount },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={debt !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[400px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>{__('Record a payment')}</DialogTitle>
                        <DialogDescription>
                            {debt?.direction === 'borrowed'
                                ? __('How much did you pay back to :name?', {
                                      name: debt.counterparty,
                                  })
                                : __('How much did :name pay you back?', {
                                      name: debt?.counterparty ?? '',
                                  })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2 py-4">
                        <Label htmlFor="debt-payment-amount">
                            {__('Amount')}
                        </Label>
                        <AmountInput
                            id="debt-payment-amount"
                            value={amount}
                            onChange={(value) => setAmount(Math.abs(value))}
                            currencyCode={debt?.currency_code ?? 'USD'}
                            required
                        />
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
                            disabled={isSubmitting || amount === 0}
                        >
                            {amount >= remaining
                                ? __('Settle debt')
                                : __('Record payment')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
