<?php

namespace Tests\Feature;

use App\Core\Support\FilterRegistry;
use App\Core\Support\SlotRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Tests\TestCase;

/**
 * FilterRegistry/SlotRegistry are static, and PHPUnit boots a fresh
 * Application per test method within the same PHP process — without
 * PluginManager::boot() flushing both registries first, every boot would
 * silently accumulate duplicate pipe/slot entries on top of whatever a
 * previous test's boot already registered (real bug, caught while building
 * the booking-history Slot — see CLAUDE.md).
 *
 * These three near-identical test methods are the regression coverage: if
 * PluginManager::boot() ever stops flushing, or a future refactor changes
 * boot order so registration happens before the flush, the counts below
 * start growing (3, 6, 9 / 1, 2, 3) instead of staying constant, and these
 * fail. A single test method can't catch this class of bug — it depends on
 * multiple sequential Application boots in the same process, which is
 * exactly what running several methods in one file exercises for real.
 */
class RegistryFlushTest extends TestCase
{
    use RefreshDatabase;

    private function assertRegistriesAreNotAccumulating(): void
    {
        $this->app->register(BookingEngineServiceProvider::class);

        $this->assertCount(
            3,
            FilterRegistry::pipesFor('booking.priceCalculation'),
            'booking.priceCalculation should have exactly its 3 real pipes (CoreDurationDiscountPipe, CoreLoyaltyDiscountPipe, CoreDepositPipe) — a higher count means FilterRegistry state leaked across Application boots.',
        );

        $this->assertCount(
            1,
            SlotRegistry::render('account.dashboardWidgets'),
            'account.dashboardWidgets should have exactly its 1 real widget (Widgets/BookingHistory) — a higher count means SlotRegistry state leaked across Application boots.',
        );
    }

    public function test_registries_do_not_accumulate_across_boot_1(): void
    {
        $this->assertRegistriesAreNotAccumulating();
    }

    public function test_registries_do_not_accumulate_across_boot_2(): void
    {
        $this->assertRegistriesAreNotAccumulating();
    }

    public function test_registries_do_not_accumulate_across_boot_3(): void
    {
        $this->assertRegistriesAreNotAccumulating();
    }
}
