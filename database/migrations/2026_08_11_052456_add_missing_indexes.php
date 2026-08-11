<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('pickup_location_id');
            $table->index('return_location_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('provider_reference');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS promo_codes_code_lower_idx ON promo_codes (LOWER(code))');
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['pickup_location_id']);
            $table->dropIndex(['return_location_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_reference']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS promo_codes_code_lower_idx');
        }
    }
};
