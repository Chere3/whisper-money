<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;

class TransferController extends Controller
{
    public function store(StoreTransferRequest $request, TransferService $transfers): RedirectResponse
    {
        $transfers->create($request->user(), $request->validated());

        // Named route rather than back(): the previous-url redirect resolves to
        // the app's internal host behind a proxy.
        return redirect()->route('transactions.index');
    }
}
