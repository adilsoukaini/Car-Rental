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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable()->after('status');

            // Backs ReleaseExpiredBookingHolds' exact query shape:
            // WHERE status = 'pending' AND hold_expires_at < now().
            $table->index(['status', 'hold_expires_at'], 'bookings_pending_hold_expiry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_pending_hold_expiry_idx');
            $table->dropColumn('hold_expires_at');
        });
    }
};
