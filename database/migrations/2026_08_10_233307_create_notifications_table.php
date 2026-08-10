<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_email')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type'); // booking_confirmed, booking_cancelled, vehicle_checked_out, vehicle_returned, etc.
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // { bookingId, bookingNumber, vehicleName, ... }
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['guest_email', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
