<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One console alert, ready for a view: what it says, how it looks, and where
 * opening it leads. Links are built here from the stored ids, not stored.
 */
final class ConsoleAlert
{
    /** Tone drives the icon colour. Coral is kept for warnings (design rule). */
    private const TONES = [
        'booking.requested' => 'attention',
        'booking.cancelled' => 'info',
        'payment.received' => 'money',
        'payment.cash' => 'money',
        'payment.refund' => 'money',
        'payment.review' => 'warning',
        'payment.proof' => 'attention',
        'chatbot.unanswered' => 'info',
    ];

    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $reference,
        public readonly int $count,
        public readonly bool $read,
        public readonly CarbonImmutable $at,
        public readonly string $tone,
        public readonly string $targetUrl,
    ) {}

    public static function from(DatabaseNotification $notification): self
    {
        $data = $notification->data;
        $kind = (string) ($data['kind'] ?? '');
        $reference = isset($data['reference']) ? (string) $data['reference'] : null;

        return new self(
            id: $notification->id,
            kind: $kind,
            title: (string) ($data['title'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            reference: $reference,
            count: (int) ($data['count'] ?? 1),
            read: $notification->read_at !== null,
            at: CarbonImmutable::instance($notification->created_at),
            tone: self::TONES[$kind] ?? 'info',
            targetUrl: match (true) {
                $reference !== null => route('staff.bookings.show', $reference),
                $kind === 'chatbot.unanswered' => route('admin.chatbot.index', ['filter' => 'fallback']),
                default => route('staff.notifications.index'),
            },
        );
    }

    /**
     * "id@unix-time" of the user's newest unread alert, or null. The bell rings
     * when this moves to a later time: a new alert, or the day's chatbot alert
     * updated in place.
     */
    public static function newestMarker(User $user): ?string
    {
        $newest = $user->unreadNotifications()->first(['id', 'created_at']);

        return $newest ? $newest->id.'@'.$newest->created_at->getTimestamp() : null;
    }
}
