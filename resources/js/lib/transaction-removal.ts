/**
 * The rows a table keeps after some of its transactions were deleted.
 *
 * Deleting one side of a transfer deletes the other on the server, so the
 * counterpart leaves the list with it instead of lingering until the next
 * reload as a row that no longer exists.
 */
export function withoutDeleted<
    T extends { id: string; transfer_id?: string | null },
>(rows: T[], deletedIds: string[]): T[] {
    const deleted = new Set(deletedIds);
    const transfers = new Set(
        rows
            .filter((row) => deleted.has(row.id) && row.transfer_id)
            .map((row) => row.transfer_id),
    );

    return rows.filter(
        (row) =>
            !deleted.has(row.id) &&
            !(row.transfer_id && transfers.has(row.transfer_id)),
    );
}

/**
 * Selected ids with one side dropped from every transfer picked whole: the
 * server removes the pair on the first request, and a second one for the other
 * side would only find it already gone.
 */
export function withoutTransferDuplicates<
    T extends { id: string; transfer_id?: string | null },
>(rows: T[], selectedIds: string[]): string[] {
    const byId = new Map(rows.map((row) => [row.id, row]));
    const seenTransfers = new Set<string>();

    return selectedIds.filter((id) => {
        const transferId = byId.get(id)?.transfer_id;

        if (!transferId) {
            return true;
        }

        if (seenTransfers.has(transferId)) {
            return false;
        }

        seenTransfers.add(transferId);

        return true;
    });
}
