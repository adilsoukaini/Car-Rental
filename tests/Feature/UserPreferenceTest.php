<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_persist_a_currency_preference(): void
    {
        $this->post('/preferences/currency', ['currency' => 'EUR'])
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_persist_currency_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/preferences/currency', ['currency' => 'EUR'])
            ->assertNoContent();

        $user->refresh();
        $this->assertSame('EUR', $user->metadata['currency']);
    }

    public function test_persisted_currency_is_shared_to_inertia_pages(): void
    {
        $user = User::factory()->create();
        $user->metadata = ['currency' => 'USD'];
        $user->save();

        // `/` is a core route (no plugin registration needed in the test
        // harness — unlike /vehicles, which the fleet-management plugin
        // registers and would 404 without its ServiceProvider in setUp()).
        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currency', 'USD')
            );
    }

    public function test_currency_is_not_shared_for_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currency', null)
            );
    }

    public function test_currency_preference_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/preferences/currency', ['currency' => 'GBP'])
            ->assertSessionHasErrors('currency');

        $user->refresh();
        $this->assertArrayNotHasKey('currency', $user->metadata ?? []);
    }
}
