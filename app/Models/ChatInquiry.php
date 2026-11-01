<?php

namespace App\Models;

use Database\Factories\ChatInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chatbot exchange: question, the resort data supplied to the model, and the answer.
 * Written only by ChatbotService; never edited afterwards.
 */
class ChatInquiry extends Model
{
    /** @use HasFactory<ChatInquiryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'was_fallback' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
