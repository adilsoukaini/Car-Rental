<?php

declare(strict_types=1);

namespace Plugins\DamageReporting;

use Illuminate\Support\ServiceProvider;

/**
 * Deliberately minimal — this plugin exists purely to own the
 * damage_reports migration (rule 6: feature-specific data goes in a
 * plugin's own migrations). The "Report Condition" action itself lives on
 * core's ViewBooking (App\Filament\Resources\Bookings\Pages\ViewBooking),
 * using only core classes (App\Models\DamageReport, App\Core\Events\DamageReported)
 * — no plugin-specific business logic, filter, or Filament resource
 * exists here, and none is added speculatively. `DamageReported` has no
 * listener yet: whether a report warrants moving the vehicle to
 * `maintenance` or capturing the deposit stays a separate, manual staff
 * decision via the existing actions, matching this project's established
 * "manual for damage, not automatic" precedent — this is deliberate, not
 * a "modeled but never consumed" gap.
 */
class DamageReportingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
