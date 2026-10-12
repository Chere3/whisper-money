import { update } from '@/actions/App/Http/Controllers/BudgetCategoryLimitController';
import { CategoryBadge } from '@/components/shared/category-combobox';
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
import { Budget, BudgetCategoryLimit } from '@/types/budget';
import { __ } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Props {
    budget: Budget;
    categoryLimits: BudgetCategoryLimit[];
    currencyCode: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/** Limits keyed by category, where 0 stands for "no limit" as in the input. */
function draftFrom(
    categoryLimits: BudgetCategoryLimit[],
): Record<string, number> {
    return Object.fromEntries(
        categoryLimits.map((limit) => [
            limit.category_id,
            limit.limit_amount ?? 0,
        ]),
    );
}

export function CategoryLimitsDialog({
    budget,
    categoryLimits,
    currencyCode,
    open,
    onOpenChange,
}: Props) {
    const [draft, setDraft] = useState<Record<string, number>>(() =>
        draftFrom(categoryLimits),
    );
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (open) {
            setDraft(draftFrom(categoryLimits));
        }
    }, [open, categoryLimits]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        router.patch(
            update({ budget: budget.id }).url,
            {
                limits: Object.entries(draft).map(([categoryId, amount]) => ({
                    category_id: categoryId,
                    limit_amount: amount > 0 ? amount : null,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>{__('Category limits')}</DialogTitle>
                        <DialogDescription>
                            {__(
                                'Cap what each category can spend per period. Leave a category empty to only count it against the budget total.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        {budget.categories?.map((category) => (
                            <div
                                key={category.id}
                                className="flex items-center justify-between gap-4"
                            >
                                <label
                                    htmlFor={`category-limit-${category.id}-input`}
                                >
                                    <CategoryBadge category={category} />
                                </label>
                                <AmountInput
                                    id={`category-limit-${category.id}-input`}
                                    className="w-40"
                                    value={draft[category.id] ?? 0}
                                    onChange={(amount) =>
                                        setDraft((current) => ({
                                            ...current,
                                            [category.id]: amount,
                                        }))
                                    }
                                    currencyCode={currencyCode}
                                    placeholder={__('No limit')}
                                />
                            </div>
                        ))}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {__('Save limits')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
