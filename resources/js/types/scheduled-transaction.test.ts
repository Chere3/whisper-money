import { describe, expect, it } from 'vitest';
import { dueStatus } from './scheduled-transaction';

describe('dueStatus', () => {
    const today = '2026-03-10';

    it.each([
        ['2026-03-09', 'overdue'],
        ['2026-03-10', 'today'],
        ['2026-03-11', 'soon'],
        ['2026-03-17', 'soon'],
        ['2026-03-18', 'later'],
        ['2027-01-01', 'later'],
    ])('reads %s as %s', (date, expected) => {
        expect(dueStatus(date, today)).toBe(expected);
    });
});
