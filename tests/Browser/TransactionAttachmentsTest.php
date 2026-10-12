<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * The upload itself is not driven from here: the browser plugin's HTTP server
 * builds its requests without files (LaravelHttpServer has a "@TODO files"),
 * so a multipart upload reaches the app empty. Uploading is covered by
 * tests/Feature/TransactionAttachmentTest.php and the component's own test;
 * this one covers what the dialog shows and removing a file from it.
 */
test('the transaction dialog lists its attachments and removes one', function () {
    Storage::fake(TransactionAttachment::DISK);

    $user = User::factory()->create(['onboarded_at' => now()]);
    $account = Account::factory()->create(['user_id' => $user->id]);
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'description' => 'Hardware store',
        'transaction_date' => today(),
    ]);
    $attachment = TransactionAttachment::factory()->create([
        'transaction_id' => $transaction->id,
        'name' => 'drill-receipt.pdf',
        'path' => UploadedFile::fake()->create('drill-receipt.pdf', 120, 'application/pdf')
            ->store("transaction-attachments/{$transaction->id}", TransactionAttachment::DISK),
    ]);

    $page = $this->actingAs($user)->visit('/transactions');
    $page->wait(2); // Wait for page to fully load

    $page->click('Hardware store')
        ->wait(2) // Wait for dialog to open and the attachments to load
        ->assertSee('Attachments')
        ->assertSee('drill-receipt.pdf')
        ->assertSee('Attach a receipt')
        ->assertAttribute(
            '[data-testid="transaction-attachments"] a',
            'href',
            "/transactions/{$transaction->id}/attachments/{$attachment->id}",
        )
        ->click('[aria-label="Remove drill-receipt.pdf"]')
        ->wait(2)
        ->assertDontSee('drill-receipt.pdf')
        ->assertNoJavascriptErrors();

    expect($transaction->attachments()->count())->toBe(0);
    Storage::disk(TransactionAttachment::DISK)->assertMissing($attachment->path);
});
