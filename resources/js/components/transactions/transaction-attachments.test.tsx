import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { TransactionAttachments } from './transaction-attachments';

vi.mock(
    '@/actions/App/Http/Controllers/TransactionAttachmentController',
    () => {
        const base = ({ transaction }: { transaction: string }) =>
            `/transactions/${transaction}/attachments`;
        const one = (params: { transaction: string; attachment: string }) => ({
            url: `${base(params)}/${params.attachment}`,
        });

        return {
            index: (params: { transaction: string }) => ({ url: base(params) }),
            store: (params: { transaction: string }) => ({ url: base(params) }),
            show: one,
            destroy: one,
        };
    },
);

vi.mock('@/lib/csrf', () => ({ getCsrfToken: () => 'token' }));

const receipt = {
    id: 'attachment-1',
    name: 'receipt.pdf',
    mime_type: 'application/pdf',
    size: 204800,
};

function respond(body: unknown, ok = true) {
    return Promise.resolve({ ok, json: () => Promise.resolve(body) });
}

describe('TransactionAttachments', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        fetchMock.mockReset();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('lists what is attached, each linking to its file', async () => {
        fetchMock.mockReturnValueOnce(respond({ data: [receipt] }));

        render(<TransactionAttachments transactionId="transaction-1" />);

        const link = await screen.findByRole('link', { name: 'receipt.pdf' });
        expect(link).toHaveAttribute(
            'href',
            '/transactions/transaction-1/attachments/attachment-1',
        );
        expect(screen.getByText('200 KB')).toBeInTheDocument();
    });

    it('uploads the picked file and adds it to the list', async () => {
        fetchMock
            .mockReturnValueOnce(respond({ data: [] }))
            .mockReturnValueOnce(respond({ data: receipt }));

        render(<TransactionAttachments transactionId="transaction-1" />);
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));

        const file = new File(['%PDF'], 'receipt.pdf', {
            type: 'application/pdf',
        });
        fireEvent.change(screen.getByTestId('transaction-attachment-input'), {
            target: { files: [file] },
        });

        expect(
            await screen.findByRole('link', { name: 'receipt.pdf' }),
        ).toBeInTheDocument();

        const [url, options] = fetchMock.mock.calls[1];
        expect(url).toBe('/transactions/transaction-1/attachments');
        expect(options.method).toBe('POST');
        expect(options.headers['X-XSRF-TOKEN']).toBe('token');
        expect(options.body.get('file')).toBe(file);
    });

    it('shows why a file was refused', async () => {
        fetchMock
            .mockReturnValueOnce(respond({ data: [] }))
            .mockReturnValueOnce(
                respond(
                    { errors: { file: ['The file is too large.'] } },
                    false,
                ),
            );

        render(<TransactionAttachments transactionId="transaction-1" />);
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));

        fireEvent.change(screen.getByTestId('transaction-attachment-input'), {
            target: { files: [new File(['x'], 'huge.pdf')] },
        });

        expect(
            await screen.findByText('The file is too large.'),
        ).toBeInTheDocument();
    });

    it('removes an attachment', async () => {
        fetchMock
            .mockReturnValueOnce(respond({ data: [receipt] }))
            .mockReturnValueOnce(respond({}));

        render(<TransactionAttachments transactionId="transaction-1" />);

        fireEvent.click(
            await screen.findByRole('button', { name: 'Remove receipt.pdf' }),
        );

        await waitFor(() =>
            expect(
                screen.queryByRole('link', { name: 'receipt.pdf' }),
            ).toBeNull(),
        );
        expect(fetchMock.mock.calls[1][1].method).toBe('DELETE');
    });

    it('stops offering the upload once the transaction is full', async () => {
        fetchMock.mockReturnValueOnce(
            respond({
                data: Array.from({ length: 5 }, (_, index) => ({
                    ...receipt,
                    id: `attachment-${index}`,
                    name: `receipt-${index}.pdf`,
                })),
            }),
        );

        render(<TransactionAttachments transactionId="transaction-1" />);

        await screen.findByRole('link', { name: 'receipt-4.pdf' });
        expect(
            screen.queryByRole('button', { name: 'Attach a receipt' }),
        ).toBeNull();
    });
});
