<?php

use Illuminate\Support\Facades\Route;
use Plugins\PaymentsStripe\Http\Controllers\StripeWebhookController;

// No 'auth'/session-based middleware — Stripe-Signature header verification
// (in StripeGateway::handleWebhook) is the authentication. CSRF is excluded
// globally for 'webhooks/*' in bootstrap/app.php.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
