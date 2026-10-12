import {
    destroy,
    index,
    show,
    store,
} from '@/actions/App/Http/Controllers/TransactionAttachmentController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { getCsrfToken } from '@/lib/csrf';
import { fetchJson } from '@/lib/fetch-json';
import { UUID } from '@/types/uuid';
import { __ } from '@/utils/i18n';
import { FileText, ImageIcon, Loader2, Paperclip, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface TransactionAttachment {
    id: UUID;
    name: string;
    mime_type: string;
    size: number;
}

/** Mirrors the server: photos and PDFs, up to this many per transaction. */
const ACCEPT = '.jpg,.jpeg,.png,.webp,.heic,.pdf';
const MAX_ATTACHMENTS = 5;

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * The receipts and invoices kept with a transaction. It loads and saves on its
 * own, apart from the dialog's form: a file is attached the moment it is
 * picked, not when the transaction is saved.
 */
export function TransactionAttachments({
    transactionId,
}: {
    transactionId: UUID;
}) {
    const [attachments, setAttachments] = useState<TransactionAttachment[]>([]);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        let cancelled = false;

        fetchJson<{ data: TransactionAttachment[] }>(
            index({ transaction: transactionId }).url,
        )
            .then(({ data }) => !cancelled && setAttachments(data))
            .catch(() => !cancelled && setAttachments([]));

        return () => {
            cancelled = true;
        };
    }, [transactionId]);

    const upload = async (file: File) => {
        setError(null);
        setIsUploading(true);

        const body = new FormData();
        body.append('file', file);

        try {
            const response = await fetch(
                store({ transaction: transactionId }).url,
                {
                    method: 'POST',
                    headers: {
                        'X-XSRF-TOKEN': getCsrfToken(),
                        Accept: 'application/json',
                    },
                    body,
                },
            );
            const json = await response.json();

            if (!response.ok) {
                setError(
                    json.errors?.file?.[0] ??
                        __('The file could not be attached.'),
                );

                return;
            }

            setAttachments((current) => [...current, json.data]);
        } catch {
            setError(__('The file could not be attached.'));
        } finally {
            setIsUploading(false);
        }
    };

    const remove = async (attachment: TransactionAttachment) => {
        const response = await fetch(
            destroy({ transaction: transactionId, attachment: attachment.id })
                .url,
            {
                method: 'DELETE',
                headers: {
                    'X-XSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            },
        );

        if (response.ok) {
            setAttachments((current) =>
                current.filter((item) => item.id !== attachment.id),
            );
        }
    };

    return (
        <div className="space-y-2" data-testid="transaction-attachments">
            <Label>{__('Attachments')}</Label>

            {attachments.length > 0 && (
                <ul className="divide-y rounded-md border">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex items-center gap-2 px-3 py-2 text-sm"
                        >
                            {attachment.mime_type.startsWith('image/') ? (
                                <ImageIcon className="size-4 shrink-0 text-muted-foreground" />
                            ) : (
                                <FileText className="size-4 shrink-0 text-muted-foreground" />
                            )}
                            {/* A plain anchor: this is a file, not a page
                                Inertia can visit. */}
                            <a
                                href={
                                    show({
                                        transaction: transactionId,
                                        attachment: attachment.id,
                                    }).url
                                }
                                target="_blank"
                                rel="noreferrer"
                                className="min-w-0 flex-1 truncate underline-offset-4 hover:underline"
                            >
                                {attachment.name}
                            </a>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {formatSize(attachment.size)}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 shrink-0"
                                aria-label={__('Remove :name', {
                                    name: attachment.name,
                                })}
                                onClick={() => remove(attachment)}
                            >
                                <X className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <input
                ref={input}
                type="file"
                accept={ACCEPT}
                className="hidden"
                data-testid="transaction-attachment-input"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    // Cleared so picking the same file again still fires.
                    event.target.value = '';

                    if (file) {
                        upload(file);
                    }
                }}
            />
            {attachments.length < MAX_ATTACHMENTS && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={isUploading}
                    onClick={() => input.current?.click()}
                >
                    {isUploading ? (
                        <Loader2 className="animate-spin" />
                    ) : (
                        <Paperclip />
                    )}
                    {__('Attach a receipt')}
                </Button>
            )}
            <p className="text-xs text-muted-foreground">
                {__('Photos or PDFs, up to 5 MB each.')}
            </p>
            {error && (
                <p className="text-sm text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
