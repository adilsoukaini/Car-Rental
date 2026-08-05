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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('category');
            $table->string('license_plate')->unique();
            $table->decimal('daily_rate', 10, 2);
            $table->unsignedTinyInteger('seat_count');
            $table->string('transmission_type');
            $table->string('fuel_type');
            $table->unsignedInteger('mileage')->default(0);
            $table->string('status')->default('available');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->json('photos')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
