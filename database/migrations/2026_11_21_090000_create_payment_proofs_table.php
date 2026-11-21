<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guest's claim that they sent money by GCash or Maya to the resort's own
 * wallet: the transfer's reference number and a screenshot of the receipt.
 * It is not money until staff verify it; then it becomes a `payments` row.
 *
 * No wallet credentials are stored: only what the guest's receipt shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // the guest who sent it
            $table->enum('method', ['gcash', 'maya']);
            $table->enum('kind', ['down', 'balance', 'full']);
            $table->decimal('amount', 10, 2);                                  // what the guest says they sent
            $table->string('reference_no', 40);                                // the wallet's transfer reference
            $table->string('screenshot_path');                                 // private disk, never public
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['method', 'reference_no']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
