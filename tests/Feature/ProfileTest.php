<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_page_includes_only_the_current_users_recent_bookings_via_the_dashboard_widget_slot(): void
    {
        $user = User::factory()->create();
        $ownBooking = Booking::factory()->create(['user_id' => $user->id]);

        $otherUser = User::factory()->create();
        Booking::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('dashboardWidgets', 1)
            ->where('dashboardWidgets.0.component', 'Widgets/BookingHistory')
            ->has('dashboardWidgets.0.props.recentBookings', 1)
            ->where('dashboardWidgets.0.props.recentBookings.0.id', $ownBooking->id)
        );
    }

    public function test_profile_page_limits_recent_bookings_to_five(): void
    {
        $user = User::factory()->create();
        Booking::factory()->count(7)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('dashboardWidgets.0.props.recentBookings', 5)
        );
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
