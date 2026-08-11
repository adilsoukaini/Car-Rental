<?php

declare(strict_types=1);

namespace Plugins\PaymentsStripe;

use App\Core\Contracts\PaymentGateway;
use App\Core\Events\PaymentAuthorized;
use App\Core\Events\PaymentCaptured;
use App\Core\Events\PaymentFailed;
use App\Core\Events\PaymentRefunded;
use App\Core\Events\PaymentReleased;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Uses the PaymentIntents API with capture_method=manual for the deposit
 * hold — NOT Checkout Sessions (which capture immediately and can't
 * represent "hold now, decide later"). See docs/event-registry.md for the
 * full lifecycle this maps to.
 */
class StripeGateway implements PaymentGateway
{
    private ?StripeClient $stripe;

    /**
     * $stripe is built lazily (see stripe()), not here — this class is
     * instantiated once at every request's boot time via
     * PaymentGatewayRegistry::register(), regardless of whether any payment
     * operation is actually used on that request. Eagerly constructing
     * StripeClient with an empty/missing secret would throw immediately and
     * take down every single page on the site the moment the plugin is
     * enabled but not yet configured with real credentials — not just
     * payment-related pages.
     */
    public function __construct(?StripeClient $stripe = null)
    {
        $this->stripe = $stripe;
    }

    private function stripe(): StripeClient
    {
        if ($this->stripe !== null) {
            return $this->stripe;
        }
        // Lazy construction — does not fail at boot time when the key is missing.
        $secret = (string) config('payments-stripe.secret');
        $this->stripe = new StripeClient([
            'api_key' => $secret,
            'stripe_version' => '2025-06-30.acacia',
        ]);
        // Retry transient network failures once (idempotent operations like
        // authorize/capture/release are safe to retry). Combined with the
        // ~30s default curl timeout this prevents a Stripe outage from
        // blocking every request for 80s (the old SDK default).
        Stripe::$maxNetworkRetries = 1;

        return $this->stripe;
    }

    public function id(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Pay by Card (Stripe)';
    }

    public function supportsDepositHold(): bool
    {
        return true;
    }

    public function authorizeDeposit(Booking $booking, float $amount): Payment
    {
        $intent = $this->stripe()->paymentIntents->create([
            'amount' => $this->toCents($amount),
            'currency' => $this->currency(),
            'capture_method' => 'manual',
            'metadata' => ['booking_id' => (string) $booking->id, 'payment_type' => 'deposit_authorization'],
        ]);

        return Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => $this->id(),
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => $intent->id,
            'metadata' => ['client_secret' => $intent->client_secret],
        ]);
    }

    public function captureDeposit(Payment $authorization, ?float $amount = null): Payment
    {
        return DB::transaction(function () use ($authorization, $amount) {
            // Atomically transition the authorization from authorized → captured.
            // This prevents concurrent capture+release from both succeeding,
            // and hides the admin UI buttons once the action completes.
            $rows = Payment::where('id', $authorization->id)
                ->where('type', 'deposit_authorization')
                ->where('status', 'authorized')
                ->lockForUpdate()
                ->update(['status' => 'captured']);

            if ($rows === 0) {
                throw new \RuntimeException('Deposit is no longer authorized — may have been captured or released already.');
            }

            $captureAmount = $amount ?? (float) $authorization->amount;

            $this->stripe()->paymentIntents->capture($authorization->provider_reference, [
                'amount_to_capture' => $this->toCents($captureAmount),
            ]);

            $capture = Payment::create([
                'booking_id' => $authorization->booking_id,
                'type' => 'deposit_capture',
                'gateway' => $this->id(),
                'status' => 'succeeded',
                'amount' => $captureAmount,
                'provider_reference' => $authorization->provider_reference,
            ]);

            event(new PaymentCaptured($capture));

            return $capture;
        });
    }

    public function releaseDeposit(Payment $authorization): Payment
    {
        return DB::transaction(function () use ($authorization) {
            // Atomically transition the authorization from authorized → released.
            $rows = Payment::where('id', $authorization->id)
                ->where('type', 'deposit_authorization')
                ->where('status', 'authorized')
                ->lockForUpdate()
                ->update(['status' => 'released']);

            if ($rows === 0) {
                throw new \RuntimeException('Deposit is no longer authorized — may have been released or captured already.');
            }

            $this->stripe()->paymentIntents->cancel($authorization->provider_reference);

            $release = Payment::create([
                'booking_id' => $authorization->booking_id,
                'type' => 'deposit_release',
                'gateway' => $this->id(),
                'status' => 'succeeded',
                'amount' => $authorization->amount,
                'provider_reference' => $authorization->provider_reference,
            ]);

            event(new PaymentReleased($release));

            return $release;
        });
    }

    public function chargeFinal(Booking $booking, float $amount): Payment
    {
        $intent = $this->stripe()->paymentIntents->create([
            'amount' => $this->toCents($amount),
            'currency' => $this->currency(),
            'metadata' => ['booking_id' => (string) $booking->id, 'payment_type' => 'final_charge'],
        ]);

        return Payment::create([
            'booking_id' => $booking->id,
            'type' => 'final_charge',
            'gateway' => $this->id(),
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => $intent->id,
        ]);
    }

    public function refund(Payment $payment, float $amount): Payment
    {
        $this->stripe()->refunds->create([
            'payment_intent' => $payment->provider_reference,
            'amount' => $this->toCents($amount),
        ]);

        $refund = Payment::create([
            'booking_id' => $payment->booking_id,
            'type' => 'refund',
            'gateway' => $this->id(),
            'status' => 'succeeded',
            'amount' => $amount,
            'provider_reference' => $payment->provider_reference,
        ]);

        event(new PaymentRefunded($refund));

        return $refund;
    }

    /**
     * See PaymentGateway::syncAuthorizationStatus() docblock for why this
     * exists alongside the webhook path, not instead of it.
     */
    public function syncAuthorizationStatus(Payment $authorization): Payment
    {
        if ($authorization->status !== 'pending') {
            return $authorization;
        }

        $intent = $this->stripe()->paymentIntents->retrieve($authorization->provider_reference);

        $this->applyIntentState($authorization, $intent, match ($intent->status) {
            'requires_capture' => 'authorized',
            'succeeded' => 'succeeded',
            'canceled' => 'failed',
            default => null, // still genuinely in progress (requires_action/confirmation/processing) — nothing to apply yet
        });

        return $authorization->fresh() ?? $authorization;
    }

    public function handleWebhook(Request $request): void
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('payments-stripe.webhook_secret');

        // Throws SignatureVerificationException or UnexpectedValueException on bad
        // input — let them bubble; the controller translates to HTTP 400.
        $stripeEvent = Webhook::constructEvent($payload, $sigHeader, $secret);

        /** @var array{object: array<string, mixed>} $eventData */
        $eventData = $stripeEvent->data->toArray();
        $intent = PaymentIntent::constructFrom($eventData['object']);

        $this->handlePaymentIntentEvent($stripeEvent, $intent);
    }

    /**
     * Applies a single Stripe webhook event to the matching pending Payment.
     *
     * H7: Stripe may deliver the same event more than once (network retry,
     * endpoint restart). The stripe_webhook_events table records every
     * processed event ID so a repeat delivery is ignored here before any
     * state is touched — a duplicate that slipped through the exists() check
     * (both copies arrived before either recorded) is absorbed by the
     * transaction: the compare-and-set status update only wins for one, and
     * the loser's insertOrIgnore hits the unique index and records nothing.
     */
    private function handlePaymentIntentEvent(Event $webhookEvent, PaymentIntent $intent): void
    {
        $eventId = $webhookEvent->id;

        if ($eventId === '' || DB::table('stripe_webhook_events')
            ->where('stripe_event_id', $eventId)
            ->exists()) {
            return; // already processed — duplicate delivery of the same Stripe event
        }

        $payment = Payment::where('provider_reference', $intent->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        // No matching pending Payment — either already processed (idempotency:
        // safe to receive the same webhook more than once) or not one of ours.
        if ($payment === null) {
            return;
        }

        DB::transaction(function () use ($webhookEvent, $eventId, $intent, $payment): void {
            $this->applyIntentState($payment, $intent, match ($webhookEvent->type) {
                'payment_intent.amount_capturable_updated' => 'authorized',
                'payment_intent.succeeded' => 'succeeded',
                'payment_intent.payment_failed' => 'failed',
                default => null, // unhandled event types are silently ignored
            });

            // Record the event ID so the exists() check above ignores a repeat
            // delivery on a later request. insertOrIgnore absorbs the race where
            // two concurrent duplicate webhooks both pass that check and both
            // reach this insert — the loser hits the unique constraint and
            // silently inserts nothing instead of throwing.
            DB::table('stripe_webhook_events')->insertOrIgnore([
                'stripe_event_id' => $eventId,
                'type' => $webhookEvent->type,
                'processed_at' => now(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Shared by both the webhook path and syncAuthorizationStatus() so a
     * webhook that arrives after the synchronous path already resolved a
     * Payment (or vice versa) applies the exact same amount cross-check and
     * lands on the exact same target status — one code path, two triggers.
     */
    private function applyIntentState(Payment $payment, PaymentIntent $intent, ?string $targetStatus): void
    {
        if ($targetStatus === null) {
            return;
        }

        // Cross-check: gateway-reported amount must match what we computed server-side.
        if ($intent->amount !== $this->toCents((float) $payment->amount)) {
            Log::error('Stripe amount mismatch — payment not marked processed', [
                'payment_id' => $payment->id,
                'expected_cents' => $this->toCents((float) $payment->amount),
                'stripe_cents' => $intent->amount,
            ]);
            $this->compareAndSet($payment, 'failed');

            return;
        }

        match ($targetStatus) {
            'authorized' => $this->markAuthorized($payment),
            'succeeded' => $this->markSucceeded($payment),
            'failed' => $this->markFailed($payment),
            default => null,
        };
    }

    private function markAuthorized(Payment $payment): void
    {
        if ($payment->type !== 'deposit_authorization') {
            return;
        }

        $this->compareAndSet($payment, 'authorized');
    }

    private function markSucceeded(Payment $payment): void
    {
        $this->compareAndSet($payment, 'succeeded');
    }

    private function markFailed(Payment $payment): void
    {
        $this->compareAndSet($payment, 'failed');
    }

    /**
     * Atomically transition a `pending` Payment to the target status.
     *
     * UPDATE ... WHERE status='pending' is a compare-and-set: only a pending
     * row may transition, and only one concurrent processor can win the
     * update. A 0-row result means a concurrent webhook/sync already resolved
     * this payment — skip the event dispatch entirely, so a duplicate
     * delivery can never double-fire PaymentAuthorized/Captured/Failed.
     */
    private function compareAndSet(Payment $payment, string $targetStatus): void
    {
        $updated = Payment::where('id', $payment->id)
            ->where('status', 'pending')
            ->update(['status' => $targetStatus]);

        if ($updated === 0) {
            return; // already transitioned — nothing left to do
        }

        $event = match ($targetStatus) {
            'authorized' => new PaymentAuthorized($payment->fresh()),
            'succeeded' => new PaymentCaptured($payment->fresh()),
            'failed' => new PaymentFailed($payment->fresh()),
            default => null,
        };

        if ($event !== null) {
            event($event);
        }
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function currency(): string
    {
        return strtolower((string) config('site.currency', 'usd'));
    }
}
