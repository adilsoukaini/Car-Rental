<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use App\Core\Exceptions\UnsupportedOperationException;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Unlike the e-commerce project's PaymentGateway (a single charge-and-refund
 * shape, since one order = one payment), a booking has multiple genuinely
 * separate financial events: authorize a deposit hold, later capture it
 * (damage) or release it (clean return), and a separate final rental
 * charge. Not every gateway can do all of these — check
 * supportsDepositHold() before calling the deposit methods; a gateway that
 * can't should throw UnsupportedOperationException from them.
 */
interface PaymentGateway
{
    /** Unique slug matching the plugin's DB slug — 'stripe' | 'cmi' */
    public function id(): string;

    /** Customer-facing label, e.g. "Pay by Card (Stripe)" */
    public function label(): string;

    /** True if this gateway can authorize a hold without capturing it (see class docblock). */
    public function supportsDepositHold(): bool;

    /**
     * Authorizes (holds) the security deposit without capturing it.
     *
     * @throws UnsupportedOperationException if !supportsDepositHold()
     */
    public function authorizeDeposit(Booking $booking, float $amount): Payment;

    /**
     * Captures a previously authorized deposit hold — full amount by
     * default, or a lesser amount (e.g. only the damage cost, releasing
     * the rest is a separate concern left to the gateway's own behavior).
     *
     * @throws UnsupportedOperationException if !supportsDepositHold()
     */
    public function captureDeposit(Payment $authorization, ?float $amount = null): Payment;

    /**
     * Releases a previously authorized deposit hold without capturing —
     * the clean-return path.
     *
     * @throws UnsupportedOperationException if !supportsDepositHold()
     */
    public function releaseDeposit(Payment $authorization): Payment;

    /**
     * Re-checks the gateway's own current status for a still-`pending`
     * deposit authorization and updates the local row if it has since
     * become authorized/failed, returning the (possibly updated) Payment.
     *
     * Added 2026-08-04 for the real checkout flow: the client-side
     * confirmation step (Stripe Elements) can complete before the async
     * webhook (`handleWebhook()`) has actually been delivered — webhooks
     * are the durable source of truth long-term (and still update the row
     * identically when they do arrive; the idempotency guard makes a
     * second update a safe no-op), but a customer waiting on their booking
     * to finalize shouldn't be stuck for however long webhook delivery
     * takes. Calling this once, synchronously, right after the client-side
     * confirmation succeeds closes that gap without weakening the webhook
     * path at all.
     *
     * @throws UnsupportedOperationException if !supportsDepositHold()
     */
    public function syncAuthorizationStatus(Payment $authorization): Payment;

    /** Charges the final rental amount outright (not a hold). */
    public function chargeFinal(Booking $booking, float $amount): Payment;

    /**
     * Issues a refund for a previously captured payment (deposit capture
     * or final charge — not an authorization, which isn't money yet).
     */
    public function refund(Payment $payment, float $amount): Payment;

    /**
     * Handles the gateway's async server-to-server callback.
     * MUST verify the request's authenticity (signature/HMAC) before trusting any payload.
     * MUST be idempotent — safe to call twice for the same event.
     */
    public function handleWebhook(Request $request): void;
}
