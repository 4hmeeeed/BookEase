<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches Table 5.4 (Database Dictionary: cottages) of the thesis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cottages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');                       // Cottage, Room, Villa, or Cabana
            $table->unsignedInteger('capacity');          // max guests; used for filtering
            $table->decimal('price_per_night', 10, 2);    // PHP; handled as string + bcmath
            $table->text('description')->nullable();      // shown to guests + chatbot context
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);  // false = delisted
            $table->timestamps();

            $table->index(['is_active', 'capacity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cottages');
    }
};
