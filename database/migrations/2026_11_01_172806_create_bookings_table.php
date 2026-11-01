<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches Table 5.5 (Database Dictionary: bookings) of the thesis.
 *
 * A booking holds the half-open interval [check_in, check_out): the
 * check-out date itself is free for the next guest (12:00 NN out, 2:00 PM in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cottage_id')->constrained()->restrictOnDelete();
            $table->string('reference_code', 20)->unique();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('guests_count');
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'cancelled', 'checked_in', 'checked_out'])
                ->default('pending');
            $table->decimal('total_price', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default('0.00');
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid', 'refunded'])
                ->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Serves the overlap query: cottage + status, then date range.
            $table->index(['cottage_id', 'status', 'check_in', 'check_out'], 'bookings_availability_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
