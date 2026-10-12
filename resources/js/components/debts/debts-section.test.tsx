import type { Debt } from '@/types/debt';
import { debtTotals } from '@/types/debt';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DebtsSection } from './debts-section';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
    visit: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({ router }));

vi.mock('@/actions/App/Http/Controllers/DebtController', () => {
    const action =
        (suffix: string) =>
        ({ debt }: { debt: string }) => ({
            url: `/debts/${debt}${suffix}`,
            method: 'put',
        });

    return {
        store: () => ({ url: '/debts', method: 'post' }),
        update: action(''),
        destroy: action(''),
        recordPayment: action('/payments'),
    };
});

vi.mock('@/hooks/use-locale', () => ({ useLocale: () => 'en-US' }));

vi.mock('@/utils/date', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/date')>()),
    todayDateString: () => '2026-03-10',
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
                onChange(Number(event.target.value || 0), false)
            }
        />
    ),
}));

function debt(overrides: Partial<Debt> = {}): Debt {
    return {
        id: 'debt-1',
        direction: 'lent',
        counterparty: 'Ana',
        description: 'Concert tickets',
        amount: 12000,
        paid_amount: 0,
        currency_code: 'USD',
        lent_on: '2026-03-01',
        due_date: null,
        settled_at: null,
        ...overrides,
    };
}

describe('debtTotals', () => {
    it('adds up what is left in each direction', () => {
        expect(
            debtTotals([
                debt({ amount: 12000, paid_amount: 2000 }),
                debt({ amount: 5000 }),
                debt({ direction: 'borrowed', amount: 3000, paid_amount: 500 }),
            ]),
        ).toEqual({ lent: 15000, borrowed: 2500 });
    });
});

describe('DebtsSection', () => {
    beforeEach(() => {
        router.post.mockReset();
    });

    it('invites the user to add a debt when there are none', () => {
        render(<DebtsSection debts={[]} currencyCode="USD" />);

        expect(
            screen.getByText(/Nothing lent or borrowed/),
        ).toBeInTheDocument();
    });

    it('totals only what is still open and tucks settled debts away', () => {
        render(
            <DebtsSection
                debts={[
                    debt({ id: 'open', amount: 12000, paid_amount: 2000 }),
                    debt({
                        id: 'owed',
                        direction: 'borrowed',
                        counterparty: 'Luis',
                        amount: 4000,
                    }),
                    debt({
                        id: 'done',
                        amount: 9000,
                        paid_amount: 9000,
                        settled_at: '2026-03-05T10:00:00Z',
                    }),
                ]}
                currencyCode="USD"
            />,
        );

        expect(screen.getByTestId('debts-total-lent')).toHaveTextContent(
            '10000',
        );
        expect(screen.getByTestId('debts-total-borrowed')).toHaveTextContent(
            '4000',
        );
        expect(screen.getByText('Settled (1)')).toBeInTheDocument();
        expect(
            within(screen.getByTestId('debt-done')).queryByRole('button', {
                name: 'Record payment',
            }),
        ).toBeNull();
    });

    it('flags a debt past its due date', () => {
        render(
            <DebtsSection
                debts={[debt({ due_date: '2026-03-01' })]}
                currencyCode="USD"
            />,
        );

        expect(screen.getByText('Overdue')).toBeInTheDocument();
    });

    it('opens the payment on what is left and posts the amount typed', () => {
        render(
            <DebtsSection
                debts={[debt({ amount: 12000, paid_amount: 2000 })]}
                currencyCode="USD"
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Record payment' }));

        const input = screen.getByTestId('debt-payment-amount');
        expect(input).toHaveValue('10000');
        expect(
            screen.getByRole('button', { name: 'Settle debt' }),
        ).toBeInTheDocument();

        fireEvent.change(input, { target: { value: '3000' } });
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Record payment',
            }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/debts/debt-1/payments',
            { amount: 3000 },
            expect.any(Object),
        );
    });
});
