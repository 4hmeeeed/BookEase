<?php

namespace App\Notifications\Console;

use Illuminate\Support\Str;

/**
 * Guests asked the assistant things it had no resort data for. One alert per
 * owner per day carries the running count (see ConsoleNotifier::chatbotMissed).
 */
class ChatbotUnanswered extends ConsoleNotification
{
    public function __construct(public readonly int $misses) {}

    public function kind(): string
    {
        return 'chatbot.unanswered';
    }

    public function title(): string
    {
        return "Questions the assistant couldn't answer";
    }

    public function body(): string
    {
        return $this->misses.' '.Str::plural('question', $this->misses)." today the assistant couldn't answer";
    }

    public function count(): int
    {
        return $this->misses;
    }
}
