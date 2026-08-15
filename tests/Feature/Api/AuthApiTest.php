<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Token-based auth API (Sanctum) for the mobile app. These run against the
 * in-memory SQLite DB, which RefreshDatabase migrates — including the Sanctum
 * `personal_access_tokens` table — so the real token issue/revoke flow is
 * exercised without touching the persistent dev database.
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_a_bearer_token_and_the_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Driver',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $response->assertJsonPath('user.email', 'jane@example.com');
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertNotNull($response->json('token'));
    }

    public function test_register_validates_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Jane Driver',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'nope',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_returns_a_bearer_token_and_the_user(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $response->assertJsonPath('user.email', 'jane@example.com');
        $this->assertNotNull($response->json('token'));
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_user_endpoint_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_user_endpoint_requires_a_token(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        // The token is gone from the DB.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Laravel's RequestGuard caches the authenticated user in the shared
        // test container, so a second request in the same test would otherwise
        // "authenticate" via the cached user even though the token is deleted.
        // Production is a fresh process per request, so this is purely a test
        // harness reset — it makes the next request re-evaluate the (deleted)
        // token, proving the revocation actually takes effect.
        Auth::forgetGuards();

        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_delete_account_requires_a_token(): void
    {
        $this->deleteJson('/api/account')->assertStatus(401);
    }

    public function test_delete_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/account', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        // The account survives a failed deletion attempt.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_deletes_the_user_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/account', ['password' => 'password123'])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_delete_account_preserves_bookings_but_anonymises_them(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        // Booking::factory() nests a Vehicle::factory(), whose Searchable trait
        // would otherwise try to index into Meilisearch (unavailable in tests).
        $booking = Vehicle::withoutSyncingToSearch(
            fn () => Booking::factory()->create(['user_id' => $user->id]),
        );
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/account', ['password' => 'password123'])
            ->assertOk();

        // The financial record is retained but no longer points at the deleted
        // user (the schema's `nullOnDelete` FK keeps the booking, nulls user_id).
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'user_id' => null]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
