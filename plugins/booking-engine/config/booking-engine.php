<?php

return [
    /*
     * Duration-discount tiers: minimum whole rental days => percent off the
     * discounted rental subtotal. Cliff/threshold model — the highest
     * threshold the booking's day count meets or exceeds wins; discounts
     * are not cumulative/graduated across tiers.
     *
     * Keys must be sorted descending so the first match found is the
     * highest applicable tier.
     */
    'duration_discount_tiers' => [
        30 => 25,
        7 => 10,
    ],

    /*
     * Security deposit = this percentage of the discounted rental subtotal.
     * Deposit is computed here but never charged by this plugin — that's a
     * separate Payment/gateway concern (see docs/event-registry.md's
     * PaymentCaptured event).
     */
    'deposit_percentage_of_subtotal' => 20,

    /*
     * How long a pending booking's availability hold (see
     * CoreAvailabilityCheckPipe's 2026-08-04 revision) stays valid while
     * the customer completes payment. ReleaseExpiredBookingHolds releases
     * anything still pending past this window.
     */
    'hold_ttl_minutes' => 15,

    /*
     * PLACEHOLDER BUSINESS VALUES — not confirmed with the actual business
     * owner, unlike duration_discount_tiers/deposit_percentage_of_subtotal
     * above (which had real numbers from day one). Retune freely, no code
     * change needed — same cliff/threshold model as duration_discount_tiers:
     * minimum whole days before pickup at the moment of cancellation =>
     * percent of the held deposit refunded (100 - this = percent forfeited
     * as a cancellation fee). The highest threshold met wins; not
     * cumulative. A cancellation with fewer days remaining than the lowest
     * key (including after pickup has already passed) refunds 0%.
     *
     * Keys must be sorted descending so the first match found is the
     * highest applicable tier.
     */
    'cancellation_refund_tiers' => [
        7 => 100,
        2 => 50,
    ],
];
