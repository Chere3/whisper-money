<?php

use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(TransactionAttachment::DISK);
});

/**
 * @return array{0: User, 1: Transaction}
 */
function userWithTransaction(): array
{
    $user = User::factory()->create(['onboarded_at' => now()]);

    return [$user, Transaction::factory()->create(['user_id' => $user->id])];
}

function attachTo(Transaction $transaction, string $name = 'receipt.pdf'): TransactionAttachment
{
    $path = UploadedFile::fake()->create($name, 120, 'application/pdf')
        ->store("transaction-attachments/{$transaction->id}", TransactionAttachment::DISK);

    return TransactionAttachment::factory()->create([
        'transaction_id' => $transaction->id,
        'path' => $path,
        'name' => $name,
    ]);
}

test('a user attaches a receipt to a transaction', function () {
    [$user, $transaction] = userWithTransaction();

    $response = $this->actingAs($user)
        ->postJson(route('transactions.attachments.store', $transaction), [
            'file' => UploadedFile::fake()->image('Dinner receipt.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Dinner receipt.jpg')
        ->assertJsonPath('data.mime_type', 'image/jpeg')
        // Where the file sits on disk is nobody's business.
        ->assertJsonMissingPath('data.path');

    $attachment = $transaction->attachments()->sole();

    expect($response->json('data.id'))->toBe($attachment->id)
        ->and($attachment->path)->toStartWith("transaction-attachments/{$transaction->id}/")
        // Stored under a generated name, not the one the user gave it.
        ->and($attachment->path)->not->toContain('Dinner');
    Storage::disk(TransactionAttachment::DISK)->assertExists($attachment->path);
});

test('only photos and pdfs up to 5 MB are accepted', function (Closure $file) {
    [$user, $transaction] = userWithTransaction();

    $this->actingAs($user)
        ->postJson(route('transactions.attachments.store', $transaction), ['file' => $file()])
        ->assertJsonValidationErrors('file');

    expect($transaction->attachments()->count())->toBe(0);
})->with([
    'an executable' => [fn () => UploadedFile::fake()->create('invoice.exe', 10, 'application/x-msdownload')],
    'a script dressed as html' => [fn () => UploadedFile::fake()->create('receipt.html', 10, 'text/html')],
    'too large' => [fn () => UploadedFile::fake()->create('scan.pdf', 5121, 'application/pdf')],
    'nothing' => [fn () => null],
]);

test('a transaction carries a limited number of attachments', function () {
    [$user, $transaction] = userWithTransaction();
    TransactionAttachment::factory()->count(TransactionAttachment::MAX_PER_TRANSACTION)->create(['transaction_id' => $transaction->id]);

    $this->actingAs($user)
        ->postJson(route('transactions.attachments.store', $transaction), [
            'file' => UploadedFile::fake()->create('one-more.pdf', 10, 'application/pdf'),
        ])
        ->assertJsonValidationErrors('file');
});

test('a user lists, opens and deletes the attachments of a transaction', function () {
    [$user, $transaction] = userWithTransaction();
    $attachment = attachTo($transaction, 'invoice.pdf');

    $this->actingAs($user)
        ->getJson(route('transactions.attachments.index', $transaction))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'invoice.pdf');

    $this->actingAs($user)
        ->get(route('transactions.attachments.show', [$transaction, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=invoice.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($user)
        ->deleteJson(route('transactions.attachments.destroy', [$transaction, $attachment]))
        ->assertOk();

    expect($transaction->attachments()->count())->toBe(0);
    // The file goes with its row.
    Storage::disk(TransactionAttachment::DISK)->assertMissing($attachment->path);
});

test('nobody else can list, open, add or delete a transaction\'s attachments', function () {
    [, $transaction] = userWithTransaction();
    $attachment = attachTo($transaction);
    $intruder = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($intruder)->getJson(route('transactions.attachments.index', $transaction))->assertForbidden();
    $this->actingAs($intruder)->get(route('transactions.attachments.show', [$transaction, $attachment]))->assertForbidden();
    $this->actingAs($intruder)->deleteJson(route('transactions.attachments.destroy', [$transaction, $attachment]))->assertForbidden();
    $this->actingAs($intruder)
        ->postJson(route('transactions.attachments.store', $transaction), [
            'file' => UploadedFile::fake()->create('planted.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect($transaction->attachments()->count())->toBe(1);
    Storage::disk(TransactionAttachment::DISK)->assertExists($attachment->path);
});

test('an attachment cannot be reached through a transaction it does not belong to', function () {
    [, $transaction] = userWithTransaction();
    $attachment = attachTo($transaction);
    [$intruder, $ownTransaction] = userWithTransaction();

    $this->actingAs($intruder)
        ->get(route('transactions.attachments.show', [$ownTransaction, $attachment]))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->deleteJson(route('transactions.attachments.destroy', [$ownTransaction, $attachment]))
        ->assertNotFound();

    Storage::disk(TransactionAttachment::DISK)->assertExists($attachment->path);
});
