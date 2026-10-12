<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionAttachmentRequest;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipts and invoices kept next to a transaction. Every action is authorized
 * through the transaction the file belongs to: an attachment has no owner of
 * its own. Reading asks for `update` too, because the transaction policy grants
 * `view` to nobody - the pages scope their queries to the user instead.
 */
class TransactionAttachmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        return response()->json([
            'data' => $transaction->attachments()->oldest()->get(),
        ]);
    }

    public function store(StoreTransactionAttachmentRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $file = $request->file('file');

        $attachment = $transaction->attachments()->create([
            // A generated name under the transaction's folder: the name the
            // user gave the file never reaches the filesystem.
            'path' => $file->store("transaction-attachments/{$transaction->id}", TransactionAttachment::DISK),
            'name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['data' => $attachment], 201);
    }

    public function show(Transaction $transaction, TransactionAttachment $attachment): StreamedResponse
    {
        $this->authorize('update', $transaction);

        // Inline so an image or a PDF opens in the browser; the stored name
        // never leaks, the one the user gave is what the file is called.
        return Storage::disk(TransactionAttachment::DISK)->response($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime_type,
            // The file came from the user: the browser renders it as the type
            // recorded at upload and never guesses another one.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Transaction $transaction, TransactionAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $transaction);

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }
}
