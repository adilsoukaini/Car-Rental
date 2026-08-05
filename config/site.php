<?php

return [
    /*
     * Active client theme. Must match a file in resources/theme/clients/<id>.ts.
     * Change this one value to re-skin the entire app for a new client.
     */
    'active_theme' => env('ACTIVE_THEME', 'default'),

    /*
     * Tenant identifier used in multi-client deployments.
     * Leave null for single-tenant setups.
     */
    'tenant_id' => env('TENANT_ID', null),

    /*
     * Site display name and locale settings.
     */
    'site_name' => env('SITE_NAME', 'Car Rental'),
    'currency' => env('SITE_CURRENCY', 'MAD'),
    'locale' => env('SITE_LOCALE', 'en'),
];
