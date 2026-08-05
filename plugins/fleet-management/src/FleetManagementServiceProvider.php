<?php

declare(strict_types=1);

namespace Plugins\FleetManagement;

use Illuminate\Support\ServiceProvider;

class FleetManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/fleet-management.php');
    }
}
