<?php

namespace Database\Factories;

use App\Models\ChatInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatInquiry>
 */
class ChatInquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_id' => (string) Str::uuid(),
            'question' => fake()->randomElement(['What time is check-in?', 'How much is a cottage per night?', 'Is Dec 24 available?']),
            'context_snapshot' => "TODAY: ...\nCOTTAGES: ...",
            'response' => 'Check-in is at 2:00 PM.',
            'was_fallback' => false,
        ];
    }

    public function fallback(): static
    {
        return $this->state(['was_fallback' => true, 'response' => 'Sorry, the assistant is not available right now.']);
    }
}
