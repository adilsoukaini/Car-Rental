<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops Vehicle.photos — a genuinely dead column (Fillable, cast to array,
 * NULL on all 41 seeded vehicles, and never read or written anywhere in
 * the codebase). Superseded by the real vehicle_images table (see the
 * vehicle-media plugin's own migration), which supports multiple images
 * per vehicle, ordering, alt text, and a real primary-image designation —
 * a crude JSON array column was never going to carry that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('photos')->nullable();
        });
    }
};
