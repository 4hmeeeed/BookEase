<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of booking lifecycle decisions: who moved a booking from which
 * status to which, when, and why. (Addition to the thesis data dictionary.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // actor; null = system
            $table->string('from_status', 20)->nullable();                          // null = created
            $table->string('to_status', 20);
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_changes');
    }
};
