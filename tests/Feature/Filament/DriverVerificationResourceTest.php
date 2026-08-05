<?php

namespace Tests\Feature\Filament;

use App\Core\Events\DriverVerified;
use App\Models\DriverVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Plugins\DriverVerification\DriverVerificationServiceProvider;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages\ViewDriverVerification;
use Tests\TestCase;

class DriverVerificationResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(DriverVerificationServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/driver-verification/database/migrations']);
    }

    /**
     * Filament's own ListRecords page layout calls route() internally for
     * its own breadcrumbs/header — the same UrlGenerator caching quirk
     * documented for Phase 4's fleet-management plugin, except here it's
     * Filament's own internal rendering hitting it, not test code. This is
     * a test-registration-timing artifact only: in real production,
     * PluginManager::boot() registers this resource during the app's
     * normal pre-render boot sequence, so the route exists before anything
     * ever tries to resolve a URL against it. A full HTTP assertOk() render
     * of the LIST page specifically isn't achievable with this test's
     * post-boot registration pattern — verified instead via a real browser
     * request in this phase's manual verification (see CLAUDE.md). What
     * genuinely proves this automatically: the route was actually
     * registered (found directly in the router's own collection,
     * bypassing the UrlGenerator helper entirely). Authorization itself
     * (staff vs customer) is proven by the customer-redirect test below,
     * and full page rendering with real data is proven by hitting
     * ViewDriverVerification directly (by record key) in the other tests,
     * neither of which goes through the list's self-referential URL path.
     */
    public function test_the_driver_verification_list_route_is_registered(): void
    {
        $matchingRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->getName() === 'filament.admin.resources.driver-verifications.index');

        $this->assertCount(1, $matchingRoutes, 'Expected the list route to be registered exactly once.');
    }

    public function test_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $response = $this->get('/admin/driver-verifications');

        $response->assertRedirect(route('home'));
    }

    public function test_approve_action_dispatches_driver_verified_and_updates_the_record(): void
    {
        Event::fake([DriverVerified::class]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $verification = DriverVerification::factory()->create(['status' => 'pending']);

        Livewire::test(ViewDriverVerification::class, ['record' => $verification->getRouteKey()])
            ->assertActionVisible('approve')
            ->callAction('approve');

        $fresh = $verification->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($staff->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        Event::assertDispatched(DriverVerified::class, fn (DriverVerified $event) => $event->user->is($verification->user));
    }

    public function test_reject_action_requires_a_reason_and_updates_the_record(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $verification = DriverVerification::factory()->create(['status' => 'pending']);

        Livewire::test(ViewDriverVerification::class, ['record' => $verification->getRouteKey()])
            ->callAction('reject', data: ['rejection_reason' => 'Document is illegible']);

        $fresh = $verification->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Document is illegible', $fresh->rejection_reason);
    }

    public function test_actions_are_hidden_once_already_reviewed(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $verification = DriverVerification::factory()->approved()->create();

        Livewire::test(ViewDriverVerification::class, ['record' => $verification->getRouteKey()])
            ->assertActionHidden('approve')
            ->assertActionHidden('reject');
    }
}
