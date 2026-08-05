<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->foreignId('pickup_location_id')->constrained('locations');
            $table->foreignId('return_location_id')->constrained('locations');
            $table->dateTime('pickup_at');
            $table->dateTime('return_at');
            $table->string('status')->default('pending');
            $table->decimal('total_price', 10, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status', 'pickup_at', 'return_at'], 'bookings_overlap_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
