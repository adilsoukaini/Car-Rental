<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();
            $table->foreignId('attribute_definition_id')
                ->constrained('vehicle_attribute_definitions')
                ->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'attribute_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_attribute_values');
    }
};
