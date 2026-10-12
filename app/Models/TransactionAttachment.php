<?php

namespace App\Models;

use Database\Factories\TransactionAttachmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A receipt or invoice attached to a transaction.
 *
 * @property string $id
 * @property string $transaction_id
 * @property string $path
 * @property string $name
 * @property string $mime_type
 * @property int $size
 */
class TransactionAttachment extends Model
{
    /** @use HasFactory<TransactionAttachmentFactory> */
    use HasFactory, HasUuids;

    /** The private disk: an attachment is only ever served to its owner. */
    public const DISK = 'local';

    /** How many files one transaction can carry. */
    public const MAX_PER_TRANSACTION = 5;

    protected $fillable = [
        'transaction_id',
        'path',
        'name',
        'mime_type',
        'size',
    ];

    /** @var list<string> */
    protected $hidden = [
        'path',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The file goes with its row, whoever deletes it.
        static::deleted(function (TransactionAttachment $attachment): void {
            Storage::disk(self::DISK)->delete($attachment->path);
        });
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
