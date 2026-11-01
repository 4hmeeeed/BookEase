<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches Table 5.6 (Database Dictionary: payments) of the thesis, plus
 * `recorded_by` (staff member who recorded a cash payment or refund).
 *
 * No wallet credentials are ever stored: guests enter them only on the
 * gateway's hosted checkout page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 40)->unique();                  // receipt / reference number
            $table->enum('method', ['gcash', 'maya', 'card', 'cash']);
            $table->enum('kind', ['down', 'balance', 'full', 'refund']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->string('provider_payment_id', 100)->nullable()->unique(); // gateway checkout/payment id
            $table->text('provider_payload')->nullable();                    // signed callback, for reconciliation
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
