<?php

namespace App\Chatbot;

final readonly class ChatReply
{
    public function __construct(
        public string $answer,
        public bool $fallback,
    ) {}
}
