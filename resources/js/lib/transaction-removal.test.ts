import { describe, expect, it } from 'vitest';
import {
    withoutDeleted,
    withoutTransferDuplicates,
} from './transaction-removal';

const rows = [
    { id: 'coffee' },
    { id: 'out', transfer_id: 'transfer-1' },
    { id: 'in', transfer_id: 'transfer-1' },
    { id: 'other-out', transfer_id: 'transfer-2' },
    { id: 'rent', transfer_id: null },
];

describe('withoutDeleted', () => {
    it('drops the deleted rows and nothing else', () => {
        expect(withoutDeleted(rows, ['coffee']).map((row) => row.id)).toEqual([
            'out',
            'in',
            'other-out',
            'rent',
        ]);
    });

    it('takes the other side of a deleted transfer with it', () => {
        expect(withoutDeleted(rows, ['in']).map((row) => row.id)).toEqual([
            'coffee',
            'other-out',
            'rent',
        ]);
    });
});

describe('withoutTransferDuplicates', () => {
    it('asks for a transfer picked whole only once', () => {
        expect(
            withoutTransferDuplicates(rows, ['coffee', 'out', 'in', 'rent']),
        ).toEqual(['coffee', 'out', 'rent']);
    });

    it('leaves separate transfers and unknown ids alone', () => {
        expect(
            withoutTransferDuplicates(rows, ['out', 'other-out', 'not-loaded']),
        ).toEqual(['out', 'other-out', 'not-loaded']);
    });
});
