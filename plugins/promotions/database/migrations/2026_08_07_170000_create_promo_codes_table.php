<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promo/discount codes, matching the e-commerce project's promo_codes
     * table but with this domain's money columns as MAD decimals (not cents).
     * `type` is 'percentage' (value = 10 for 10% off the subtotal) or
     * 'fixed' (value = 100 for 100.00 MAD off the subtotal).
     *
     * uses_count is incremented when a booking carrying this code is
     * CONFIRMED (via the promotions plugin's BookingConfirmed listener),
     * never during read-only price previews — see PromoCodePipe's docblock.
     */
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');                          // percentage | fixed
            $table->decimal('value', 10, 2);                 // 10 = 10%, 100 = 100.00 MAD
            $table->decimal('min_booking_amount', 10, 2)->nullable(); // MAD minimum subtotal
            $table->integer('max_uses')->nullable();         // null = unlimited
            $table->integer('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
