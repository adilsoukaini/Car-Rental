<?php

namespace Tests\Feature\Reviews;

use App\Core\Events\ReviewSubmitted;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Plugins\Reviews\ReviewsServiceProvider;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(ReviewsServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/reviews/database/migrations']);
    }

    public function test_a_guest_cannot_submit_a_review(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 5,
            'body' => 'Great car.',
        ]);

        $response->assertRedirect('/login');
        $this->assertSame(0, Review::count());
    }

    public function test_an_authenticated_user_can_submit_a_review(): void
    {
        Event::fake([ReviewSubmitted::class]);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($user)->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 4,
            'title' => 'Solid ride',
            'body' => 'Comfortable and clean.',
        ]);

        $response->assertRedirect();

        $review = Review::first();
        $this->assertNotNull($review);
        $this->assertSame($vehicle->id, $review->vehicle_id);
        $this->assertSame($user->id, $review->user_id);
        $this->assertSame(4, $review->rating);
        $this->assertFalse($review->is_approved);

        Event::assertDispatched(ReviewSubmitted::class, fn (ReviewSubmitted $event) => $event->review->is($review));
    }

    public function test_submitting_a_review_with_a_returned_booking_marks_it_verified(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        Booking::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'returned']);

        $this->actingAs($user)->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 5,
            'body' => 'Excellent.',
        ]);

        $this->assertTrue(Review::first()->is_verified_rental);
    }

    public function test_submitting_a_review_with_no_returned_booking_is_not_verified(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 5,
            'body' => 'Never actually rented it.',
        ]);

        $this->assertFalse(Review::first()->is_verified_rental);
    }

    public function test_a_user_cannot_review_the_same_vehicle_twice(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 5,
            'body' => 'First review.',
        ]);

        $response = $this->actingAs($user)->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 1,
            'body' => 'Second attempt.',
        ]);

        $response->assertSessionHasErrors(['review']);
        $this->assertSame(1, Review::count());
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($user)->post("/vehicles/{$vehicle->id}/reviews", [
            'rating' => 6,
            'body' => 'Too high.',
        ]);

        $response->assertSessionHasErrors(['rating']);
    }
}
