<?php

namespace App\Notifications\Console;

use Illuminate\Notifications\Notification;

/**
 * An alert for the staff/owner console (the bell), stored in the database only.
 *
 * Every alert keeps the same small shape, so the console can list it without
 * loading anything else and no contact or wallet details ever end up in it.
 */
abstract class ConsoleNotification extends Notification
{
    abstract public function kind(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    /** Reference code of the booking the alert opens, if any. */
    public function reference(): ?string
    {
        return null;
    }

    /** How many events this one alert stands for. */
    public function count(): int
    {
        return 1;
    }

    /**
     * Reference number of the payment the alert is about, if any, so the
     * console can count payments rather than alerts.
     */
    public function paymentReference(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    final public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string, reference: ?string, count: int, payment?: string}
     */
    final public function toArray(object $notifiable): array
    {
        $data = [
            'kind' => $this->kind(),
            'title' => $this->title(),
            'body' => $this->body(),
            'reference' => $this->reference(),
            'count' => $this->count(),
        ];

        if (($payment = $this->paymentReference()) !== null) {
            $data['payment'] = $payment;
        }

        return $data;
    }
}
