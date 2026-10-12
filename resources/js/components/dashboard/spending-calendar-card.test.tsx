import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
    calendarWeeks,
    SpendingCalendarCard,
    spendingLevel,
} from './spending-calendar-card';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => (
        <a {...props}>{children}</a>
    ),
    usePage: () => ({
        props: { auth: { user: { currency_code: 'USD' } } },
    }),
}));

vi.mock('@/actions/App/Http/Controllers/TransactionController', () => ({
    index: ({ query }: { query: Record<string, string> }) => ({
        url: `/transactions?date_from=${query.date_from}&date_to=${query.date_to}`,
    }),
}));

vi.mock('@/hooks/use-locale', () => ({ useLocale: () => 'en-US' }));

vi.mock('@/components/privacy-reveal-button', () => ({
    PrivacyRevealButton: () => null,
}));

vi.mock('@/components/ui/amount-display', () => ({
    AmountDisplay: ({ amountInCents }: { amountInCents: number }) => (
        <span>{amountInCents}</span>
    ),
}));

describe('spendingLevel', () => {
    it.each([
        [0, 1000, 0],
        [1, 1000, 1],
        [250, 1000, 1],
        [251, 1000, 2],
        [750, 1000, 3],
        [1000, 1000, 4],
        [500, 0, 0],
    ])('shades %i out of %i as level %i', (amount, max, level) => {
        expect(spendingLevel(amount, max)).toBe(level);
    });
});

describe('calendarWeeks', () => {
    it('splits the window into weeks and stops the last one at today', () => {
        const weeks = calendarWeeks('2026-03-02', '2026-03-12');

        expect(weeks).toHaveLength(2);
        expect(weeks[0]).toEqual([
            '2026-03-02',
            '2026-03-03',
            '2026-03-04',
            '2026-03-05',
            '2026-03-06',
            '2026-03-07',
            '2026-03-08',
        ]);
        expect(weeks[1]).toEqual([
            '2026-03-09',
            '2026-03-10',
            '2026-03-11',
            '2026-03-12',
        ]);
    });
});

describe('SpendingCalendarCard', () => {
    const calendar = {
        from: '2026-03-02',
        to: '2026-03-12',
        days: { '2026-03-03': 1000, '2026-03-10': 4000 },
    };

    it('draws a cell per day, shaded by what was spent, linking to that day', () => {
        render(<SpendingCalendarCard calendar={calendar} />);

        expect(screen.getAllByRole('link')).toHaveLength(11);

        const priciest = screen.getByLabelText('Mar 10, 2026');
        expect(priciest).toHaveAttribute('data-level', '4');
        expect(priciest).toHaveAttribute(
            'href',
            '/transactions?date_from=2026-03-10&date_to=2026-03-10',
        );
        expect(screen.getByLabelText('Mar 3, 2026')).toHaveAttribute(
            'data-level',
            '1',
        );
        expect(screen.getByLabelText('Mar 4, 2026')).toHaveAttribute(
            'data-level',
            '0',
        );
    });

    it('sums the window up and names its most expensive day', () => {
        render(<SpendingCalendarCard calendar={calendar} />);

        expect(screen.getByText('5000')).toBeInTheDocument();
        expect(screen.getByText('4000')).toBeInTheDocument();
        expect(screen.getByText(/Mar 10/)).toBeInTheDocument();
    });

    it('draws nothing when nothing was spent', () => {
        const { container } = render(
            <SpendingCalendarCard calendar={{ ...calendar, days: {} }} />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
