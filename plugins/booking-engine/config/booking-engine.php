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
];
