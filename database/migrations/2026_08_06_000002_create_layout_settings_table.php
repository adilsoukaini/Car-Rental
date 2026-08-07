<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_settings', function (Blueprint $table) {
            $table->id();
            $table->string('slot_name')->unique();
            $table->string('active_variant_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_settings');
    }
};
