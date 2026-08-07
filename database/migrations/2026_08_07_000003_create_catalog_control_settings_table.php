<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-control enable/disable settings for the storefront fleet listing.
     * One row per (control_type, control_id) — 'filter' or 'sort' paired with
     * the registry ID (e.g. 'transmission', 'price_asc'). Absence of a row
     * means "enabled" (the pre-admin-control default); the
     * CatalogControlSettings admin page upserts rows, and
     * VehicleFilterRegistry / VehicleSortRegistry consult them when applying
     * filters and resolving sorts. `sort_order` records display ordering for
     * a future reorder UI (the registries themselves keep registration
     * order today).
     */
    public function up(): void
    {
        Schema::create('catalog_control_settings', function (Blueprint $table) {
            $table->id();
            $table->string('control_type'); // 'filter' | 'sort'
            $table->string('control_id'); // registry ID, e.g. 'transmission'
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['control_type', 'control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_control_settings');
    }
};
