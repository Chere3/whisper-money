import { UUID } from './uuid';

export const DEBT_DIRECTIONS = ['lent', 'borrowed'] as const;

export type DebtDirection = (typeof DEBT_DIRECTIONS)[number];

/**
 * Money owed between the user and another person. It is tracked on its own:
 * no transaction is created for it.
 */
export interface Debt {
    id: UUID;
    /** `lent`: the user is owed it. `borrowed`: the user owes it. */
    direction: DebtDirection;
    counterparty: string;
    description: string | null;
    /** The whole debt, in minor units of `currency_code`. Always positive. */
    amount: number;
    paid_amount: number;
    currency_code: string;
    lent_on: string;
    due_date: string | null;
    settled_at: string | null;
}

export function debtRemaining(debt: Debt): number {
    return Math.max(0, debt.amount - debt.paid_amount);
}

/** What is still open in each direction, across the debts given. */
export function debtTotals(debts: Debt[]): Record<DebtDirection, number> {
    return debts.reduce(
        (totals, debt) => ({
            ...totals,
            [debt.direction]: totals[debt.direction] + debtRemaining(debt),
        }),
        { lent: 0, borrowed: 0 },
    );
}
