import type { Budget, BudgetCategoryLimit } from '@/types/budget';
import type { Category } from '@/types/category';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BudgetCategoryLimits } from './budget-category-limits';

const patch = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    router: { patch },
}));

vi.mock('@/actions/App/Http/Controllers/BudgetCategoryLimitController', () => ({
    update: () => ({ url: '/budgets/budget-1/category-limits' }),
}));

vi.mock('@/components/ui/amount-display', () => ({
    AmountDisplay: ({ amountInCents }: { amountInCents: number }) => (
        <span>{amountInCents}</span>
    ),
}));

vi.mock('@/components/ui/amount-input', () => ({
    AmountInput: ({
        id,
        value,
        onChange,
    }: {
        id: string;
        value: number;
        onChange: (value: number, isEmpty: boolean) => void;
    }) => (
        <input
            id={id}
            data-testid={id}
            value={value === 0 ? '' : value}
            onChange={(event) =>
                onChange(
                    Number(event.target.value || 0),
                    event.target.value === '',
                )
            }
        />
    ),
}));

function makeCategory(id: string, name: string): Category {
    return { id, name, icon: 'Tag', color: 'blue' } as Category;
}

function makeBudget(overrides: Partial<Budget> = {}): Budget {
    return {
        id: 'budget-1',
        user_id: 'user-1',
        name: 'Food',
        period_type: 'monthly',
        period_start_day: 1,
        rollover_type: 'reset',
        is_catch_all: false,
        position: null,
        archived_at: null,
        notify_on_new_transaction: false,
        notify_on_close_to_limit: true,
        notify_on_over_limit: true,
        created_at: '2026-05-26T00:00:00.000000Z',
        updated_at: '2026-05-26T00:00:00.000000Z',
        deleted_at: null,
        categories: [
            makeCategory('groceries', 'Groceries'),
            makeCategory('dining', 'Dining'),
        ],
        labels: [],
        ...overrides,
    };
}

const limits: BudgetCategoryLimit[] = [
    { category_id: 'groceries', limit_amount: 30000, spent: 15000 },
    { category_id: 'dining', limit_amount: null, spent: 4500 },
];

describe('BudgetCategoryLimits', () => {
    beforeEach(() => {
        patch.mockReset();
    });

    it('shows the spending of each tracked category against its limit', () => {
        render(
            <BudgetCategoryLimits
                budget={makeBudget()}
                categoryLimits={limits}
                currencyCode="USD"
            />,
        );

        const groceries = screen.getByTestId('category-limit-groceries');
        expect(within(groceries).getByText('15000')).toBeInTheDocument();
        expect(within(groceries).getByText('30000')).toBeInTheDocument();
        expect(within(groceries).getByRole('progressbar')).toBeInTheDocument();

        const dining = screen.getByTestId('category-limit-dining');
        expect(within(dining).getByText('4500')).toBeInTheDocument();
        expect(within(dining).getByText(/No limit/)).toBeInTheDocument();
        expect(within(dining).queryByRole('progressbar')).toBeNull();
    });

    it('renders nothing for a budget that tracks no categories', () => {
        const { container } = render(
            <BudgetCategoryLimits
                budget={makeBudget({ categories: [], is_catch_all: true })}
                categoryLimits={[]}
                currencyCode="USD"
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('keeps an archived budget read-only', () => {
        render(
            <BudgetCategoryLimits
                budget={makeBudget({ archived_at: '2026-06-01T00:00:00Z' })}
                categoryLimits={limits}
                currencyCode="USD"
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Edit limits' }),
        ).toBeNull();
    });

    it('saves the limits, sending an emptied one as null', () => {
        render(
            <BudgetCategoryLimits
                budget={makeBudget()}
                categoryLimits={limits}
                currencyCode="USD"
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Edit limits' }));
        fireEvent.change(screen.getByTestId('category-limit-groceries-input'), {
            target: { value: '' },
        });
        fireEvent.change(screen.getByTestId('category-limit-dining-input'), {
            target: { value: '8000' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Save limits' }));

        expect(patch).toHaveBeenCalledWith(
            '/budgets/budget-1/category-limits',
            {
                limits: [
                    { category_id: 'groceries', limit_amount: null },
                    { category_id: 'dining', limit_amount: 8000 },
                ],
            },
            expect.any(Object),
        );
    });
});
