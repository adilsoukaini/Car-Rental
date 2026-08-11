<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * H6: idempotency_key lets BookingCheckoutController::store() return an
     * already-created booking when a client retries the same request (e.g.
     * the first response was lost in transit). The unique constraint is what
     * makes the retry safe: two concurrent attempts with the same key cannot
     * both insert a booking row.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
