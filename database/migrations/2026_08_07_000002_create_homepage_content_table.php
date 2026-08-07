<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton settings row: id=1 always holds the admin-editable homepage
     * hero/content copy (hero headline/subtitle/CTA, features section
     * heading/subtitle, CTA band heading/subtitle). Exactly one row by
     * convention — the HomepageContentSettings page reads/upserts id=1, and
     * the `/` route shares it to the storefront Home page.
     */
    public function up(): void
    {
        Schema::create('homepage_content', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title');
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_cta_text')->nullable();
            $table->string('hero_cta_link')->nullable();
            $table->string('features_title')->nullable();
            $table->text('features_subtitle')->nullable();
            $table->string('cta_band_title')->nullable();
            $table->text('cta_band_subtitle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_content');
    }
};
