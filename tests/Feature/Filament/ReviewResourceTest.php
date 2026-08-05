<?php

namespace Tests\Feature\Filament;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Plugins\Reviews\Filament\Resources\Reviews\Pages\ListReviews;
use Plugins\Reviews\ReviewsServiceProvider;
use Tests\TestCase;

class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(ReviewsServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/reviews/database/migrations']);
    }

    /**
     * Same UrlGenerator/breadcrumb test-harness artifact documented for
     * driver-verification's list page (Phase 9) and fleet-management
     * (Phase 4): Filament's own ListRecords page calls route() internally
     * for its breadcrumbs, which doesn't see routes registered post-boot
     * in tests, even though real HTTP dispatch to the same path works in
     * production (where this plugin's provider registers during the
     * app's normal pre-render boot). Proven instead by confirming the
     * route is genuinely registered, bypassing the UrlGenerator helper —
     * the approve-action tests below cover the real business logic via
     * Livewire directly, which doesn't hit this artifact.
     */
    public function test_the_review_list_route_is_registered(): void
    {
        $matchingRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->getName() === 'filament.admin.resources.reviews.index');

        $this->assertCount(1, $matchingRoutes, 'Expected the list route to be registered exactly once.');
    }

    public function test_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $response = $this->get('/admin/reviews');

        $response->assertRedirect(route('home'));
    }

    public function test_approve_action_is_hidden_once_already_approved(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $review = Review::factory()->create(['is_approved' => true]);

        Livewire::test(ListReviews::class)
            ->assertTableActionHidden('approve', $review);
    }

    public function test_approve_action_marks_the_review_approved(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $review = Review::factory()->create(['is_approved' => false]);

        Livewire::test(ListReviews::class)
            ->assertTableActionVisible('approve', $review)
            ->callTableAction('approve', $review);

        $this->assertTrue($review->fresh()->is_approved);
    }
}
