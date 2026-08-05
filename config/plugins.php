<?php

use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\DriverVerification\DriverVerificationServiceProvider;
use Plugins\FleetManagement\FleetManagementServiceProvider;
use Plugins\PaymentsStripe\StripeServiceProvider;

return [
    /*
     * Maps plugin slug -> Service Provider FQCN.
     * Only slugs that are also enabled in the `plugins` DB table will have
     * their provider registered at boot time via PluginManager.
     */
    'registry' => [
        'fleet-management' => FleetManagementServiceProvider::class,
        'booking-engine' => BookingEngineServiceProvider::class,
        'payments-stripe' => StripeServiceProvider::class,
        'driver-verification' => DriverVerificationServiceProvider::class,
    ],

    /*
     * Static manifest of all payment gateways — declared here so they can
     * appear in a checkout UI (e.g. grayed-out as "coming soon") even when
     * their plugin is disabled. Disabled plugins never boot their
     * ServiceProvider, so they never call PaymentGatewayRegistry::register();
     * this manifest is the source of truth for what gateways *exist*, while
     * the DB flag controls what's *active*.
     *
     * Keys match the gateway's id() return value.
     */
    'gateways' => [
        'stripe' => [
            'plugin' => 'payments-stripe',
            'label' => 'Pay by Card (Stripe)',
            'supportsDepositHold' => true,
        ],
    ],
];
