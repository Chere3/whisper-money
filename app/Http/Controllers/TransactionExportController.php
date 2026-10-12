<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionCsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    /**
     * Download the user's transactions as CSV, narrowed by the same filters the
     * transactions page takes. Rows are streamed so the whole history never
     * has to fit in memory.
     */
    public function __invoke(IndexTransactionRequest $request, TransactionCsvExporter $exporter): StreamedResponse
    {
        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->with(TransactionCsvExporter::RELATIONS)
            ->applyFilters($request->filters())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->lazy(500);

        return response()->streamDownload(function () use ($transactions, $exporter): void {
            $output = fopen('php://output', 'w');

            // The byte order mark is what makes Excel read the file as UTF-8.
            fwrite($output, "\u{FEFF}");
            fputcsv($output, TransactionCsvExporter::HEADER, escape: '');

            foreach ($transactions as $transaction) {
                fputcsv($output, $exporter->row($transaction), escape: '');
            }

            fclose($output);
        }, 'whisper-money-transactions-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
