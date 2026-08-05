<?php

declare(strict_types=1);

namespace Plugins\PaymentsStripe;

use App\Core\Support\PaymentGatewayRegistry;
use Illuminate\Support\ServiceProvider;

class StripeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payments-stripe.php', 'payments-stripe');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');

        PaymentGatewayRegistry::register(new StripeGateway, 'payments-stripe');
    }
}
