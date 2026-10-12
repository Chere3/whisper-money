import { CategoryLimitsDialog } from '@/components/budgets/category-limits-dialog';
import { CategoryBadge } from '@/components/shared/category-combobox';
import { AmountDisplay } from '@/components/ui/amount-display';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import {
    Budget,
    BudgetCategoryLimit,
    getBudgetSeverityColor,
    severityForPercentage,
} from '@/types/budget';
import { __ } from '@/utils/i18n';
import { useState } from 'react';

interface Props {
    budget: Budget;
    categoryLimits: BudgetCategoryLimit[];
    currencyCode: string;
}

/**
 * What each tracked category spent in the period on screen, against the limit
 * it carries inside this budget. A category without a limit still shows its
 * spending, so the card doubles as the budget's breakdown.
 */
export function BudgetCategoryLimits({
    budget,
    categoryLimits,
    currencyCode,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const categories = budget.categories ?? [];
    const archived = budget.archived_at !== null;

    if (categories.length === 0 || categoryLimits.length === 0) {
        return null;
    }

    const limitByCategory = new Map(
        categoryLimits.map((limit) => [limit.category_id, limit]),
    );
    const hasLimits = categoryLimits.some(
        (limit) => limit.limit_amount !== null,
    );

    return (
        <div
            className="space-y-4 rounded-lg border border-border bg-card p-6"
            data-testid="budget-category-limits"
        >
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h3 className="text-sm font-medium">
                        {__('Category limits')}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {__(
                            'How much each tracked category has spent in this period.',
                        )}
                    </p>
                </div>
                {!archived && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditOpen(true)}
                    >
                        {hasLimits ? __('Edit limits') : __('Set limits')}
                    </Button>
                )}
            </div>

            <ul className="space-y-4">
                {categories.map((category) => {
                    const limit = limitByCategory.get(category.id);
                    const spent = limit?.spent ?? 0;
                    const limitAmount = limit?.limit_amount ?? null;
                    const percentage =
                        limitAmount === null ? 0 : (spent / limitAmount) * 100;

                    return (
                        <li
                            key={category.id}
                            className="space-y-2"
                            data-testid={`category-limit-${category.id}`}
                        >
                            <div className="flex items-center justify-between gap-2 text-sm">
                                <CategoryBadge category={category} />
                                {limitAmount === null ? (
                                    <span className="text-muted-foreground">
                                        <AmountDisplay
                                            amountInCents={spent}
                                            currencyCode={currencyCode}
                                        />{' '}
                                        · {__('No limit')}
                                    </span>
                                ) : (
                                    <span
                                        className={getBudgetSeverityColor(
                                            severityForPercentage(percentage),
                                        )}
                                    >
                                        <AmountDisplay
                                            amountInCents={spent}
                                            currencyCode={currencyCode}
                                        />{' '}
                                        {__('of')}{' '}
                                        <AmountDisplay
                                            amountInCents={limitAmount}
                                            currencyCode={currencyCode}
                                        />
                                    </span>
                                )}
                            </div>
                            {limitAmount !== null && (
                                <Progress
                                    value={Math.min(percentage, 100)}
                                    className="h-2"
                                />
                            )}
                        </li>
                    );
                })}
            </ul>

            <CategoryLimitsDialog
                budget={budget}
                categoryLimits={categoryLimits}
                currencyCode={currencyCode}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
        </div>
    );
}
