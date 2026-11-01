<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot audit log. Matches Table 5.7 (Database Dictionary: chat_inquiries).
 * Every question is stored with the exact resort data the model was given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null for visitors
            $table->string('session_id')->nullable();          // random chat id, not the Laravel session id
            $table->text('question');
            $table->text('context_snapshot')->nullable();
            $table->text('response')->nullable();
            $table->boolean('was_fallback')->default(false);
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
            $table->index(['was_fallback', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_inquiries');
    }
};
