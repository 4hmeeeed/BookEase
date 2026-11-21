<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Self-service account deletion from the profile page.
 *
 * Deleting a user cascades to their bookings, payments, and booking history,
 * so an account may be deleted only when nothing the resort has to keep would
 * go with it. Staff and the owner are never deleted: their names stay on the
 * payments and booking decisions they recorded (see StaffService).
 */
class AccountDeletion
{
    /** Why this account can't be deleted, or null when it can. */
    public function refusal(User $user): ?string
    {
        if (! $user->isGuest()) {
            return 'Staff and owner accounts can’t be deleted. The owner can deactivate a staff account from the Staff page instead.';
        }

        if ($user->bookings()->whereIn('status', BookingStatus::blockingValues())->exists()) {
            return 'You have an upcoming or ongoing booking. Please cancel it first, or contact the resort.';
        }

        $hasPaymentRecords = Payment::query()
            ->whereRelation('booking', 'user_id', $user->id)
            ->where('status', PaymentRecordStatus::Paid)
            ->exists();

        if ($hasPaymentRecords) {
            return 'Your account has payment records the resort is required to keep. Please contact the resort to close your account.';
        }

        return null;
    }

    /**
     * Delete the account together with the staff alerts about the guest's
     * bookings, whose text names them (RA 10173). Only call this when
     * refusal() returned null.
     */
    public function delete(User $user): void
    {
        // Rows cascade with the user; their screenshot files don't.
        $screenshots = PaymentProof::query()->where('user_id', $user->id)->pluck('screenshot_path')->all();

        DB::transaction(function () use ($user) {
            $references = $user->bookings()->pluck('reference_code')->all();

            if ($references !== []) {
                DatabaseNotification::query()->whereIn('data->reference', $references)->delete();
            }

            $user->delete();
        });

        if ($screenshots !== []) {
            Storage::disk(PaymentProof::DISK)->delete($screenshots);
        }
    }
}
