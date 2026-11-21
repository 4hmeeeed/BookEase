<?php

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProofStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest's GCash/Maya transfer to the resort's wallet, waiting for (or past)
 * a staff check. Every attribute is set by WalletTransferService.
 *
 * @property string $amount 2-decimal string (bcmath)
 */
class PaymentProof extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['screenshot_path'];

    /** Private disk: screenshots are served only through an authorised route. */
    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'kind' => PaymentKind::class,
            'status' => PaymentProofStatus::class,
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentProofStatus::Pending;
    }
}
