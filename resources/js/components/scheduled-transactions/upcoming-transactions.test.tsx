import type { Account } from '@/types/account';
import type { ScheduledTransaction } from '@/types/scheduled-transaction';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { UpcomingTransactions } from './upcoming-transactions';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
    visit: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({ router }));

vi.mock('@/actions/App/Http/Controllers/ScheduledTransactionController', () => {
    const action =
        (suffix: string) =>
        ({ scheduledTransaction }: { scheduledTransaction: string }) => ({
            url: `/scheduled-transactions/${scheduledTransaction}${suffix}`,
        });

    return {
        store: () => ({ url: '/scheduled-transactions', method: 'post' }),
        update: action(''),
        destroy: action(''),
        pay: action('/pay'),
        skip: action('/skip'),
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

vi.mock('./scheduled-transaction-dialog', () => ({
    ScheduledTransactionDialog: ({
        open,
        scheduledTransaction,
    }: {
        open: boolean;
        scheduledTransaction?: ScheduledTransaction;
    }) =>
        open ? (
            <div data-testid="schedule-dialog">
                {scheduledTransaction?.description ?? 'new'}
            </div>
        ) : null,
}));

const checking = {
    id: 'account-1',
    name: 'Checking',
    type: 'checking',
    currency_code: 'USD',
    archived_at: null,
} as Account;

function scheduled(
    overrides: Partial<ScheduledTransaction> = {},
): ScheduledTransaction {
    return {
        id: 'scheduled-1',
        account_id: 'account-1',
        category_id: null,
        description: 'Rent',
        amount: -95000,
        currency_code: 'USD',
        notes: null,
        frequency: 'monthly',
        next_due_date: '2026-03-20',
        end_date: null,
        account: checking,
        category: null,
        ...overrides,
    };
}

function renderSection(
    scheduledTransactions: ScheduledTransaction[],
    accounts: Account[] = [checking],
) {
    return render(
        <UpcomingTransactions
            scheduledTransactions={scheduledTransactions}
            accounts={accounts}
            categories={[]}
        />,
    );
}

describe('UpcomingTransactions', () => {
    beforeEach(() => {
        router.post.mockReset();
        router.delete.mockReset();
    });

    it('invites the user to schedule something when the list is empty', () => {
        renderSection([]);

        expect(screen.getByText(/Nothing scheduled yet/)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Schedule' }));
        expect(screen.getByTestId('schedule-dialog')).toHaveTextContent('new');
    });

    it('cannot schedule without an account that holds transactions', () => {
        renderSection([], [{ ...checking, type: 'investment' } as Account]);

        expect(screen.getByRole('button', { name: 'Schedule' })).toBeDisabled();
    });

    it('flags what is overdue and what is due today', () => {
        renderSection([
            scheduled({ id: 'late', next_due_date: '2026-03-01' }),
            scheduled({ id: 'now', next_due_date: '2026-03-10' }),
            scheduled({ id: 'later', next_due_date: '2026-04-20' }),
        ]);

        expect(
            within(screen.getByTestId('scheduled-transaction-late')).getByText(
                /^Overdue · Mar 1, 2026$/,
            ),
        ).toBeInTheDocument();
        expect(
            within(screen.getByTestId('scheduled-transaction-now')).getByText(
                'Due today',
            ),
        ).toBeInTheDocument();
        expect(
            within(screen.getByTestId('scheduled-transaction-later')).getByText(
                'Apr 20, 2026',
            ),
        ).toBeInTheDocument();
    });

    it('pays and skips through their own endpoints', () => {
        // The row stays locked until the request settles.
        router.post.mockImplementation((_url, _data, options) =>
            options.onFinish(),
        );
        renderSection([scheduled()]);

        fireEvent.click(screen.getByRole('button', { name: 'Mark as paid' }));
        expect(router.post).toHaveBeenLastCalledWith(
            '/scheduled-transactions/scheduled-1/pay',
            {},
            expect.any(Object),
        );

        fireEvent.click(screen.getByRole('button', { name: 'Skip' }));
        expect(router.post).toHaveBeenLastCalledWith(
            '/scheduled-transactions/scheduled-1/skip',
            {},
            expect.any(Object),
        );
    });

    it('locks the row while a payment is on its way', () => {
        renderSection([scheduled()]);

        fireEvent.click(screen.getByRole('button', { name: 'Mark as paid' }));

        expect(screen.getByRole('button', { name: 'Skip' })).toBeDisabled();
    });

    it('words the action for an income as received', () => {
        renderSection([scheduled({ description: 'Salary', amount: 250000 })]);

        expect(
            screen.getByRole('button', { name: 'Mark as received' }),
        ).toBeInTheDocument();
    });
});
