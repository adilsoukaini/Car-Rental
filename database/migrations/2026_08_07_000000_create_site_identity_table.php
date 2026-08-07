<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton settings row: id=1 always holds the current site identity
     * (site name, logo, favicon). Exactly one row by convention — the
     * SiteIdentitySettings page reads/upserts id=1.
     */
    public function up(): void
    {
        Schema::create('site_identity', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_identity');
    }
};
