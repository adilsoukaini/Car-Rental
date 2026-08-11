<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * H7: deduplicate Stripe webhook deliveries by event ID. Stripe may
     * deliver the same event more than once (network retries, endpoint
     * restarts); the unique stripe_event_id lets handlePaymentIntentEvent()
     * ignore a repeat delivery instead of re-applying it (which could
     * double-fire PaymentAuthorized/Captured/Failed events).
     */
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
