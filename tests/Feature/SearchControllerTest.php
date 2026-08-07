<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the storefront search-autocomplete endpoint (SearchController,
 * GET /search/suggestions).
 *
 * Scout runs in its `database` engine in this project (SCOUT_DRIVER=database
 * in .env), so the search performs a plain LIKE/ILIKE against the real
 * `vehicles` table columns listed in Vehicle::toSearchableArray() — no
 * separate index, no scout:import needed inside the automated suite. The
 * vehicle-media plugin is not registered in these tests, so `imageUrl`
 * degrades to null and the dynamic `primaryImage` relation is never
 * batch-loaded — both the null fallback and the response shape are asserted.
 */
class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_route_is_registered(): void
    {
        $this->assertTrue(Route::has('search.suggestions'));
    }

    public function test_suggestions_returns_matching_available_vehicles_with_the_expected_shape(): void
    {
        $toyota = Vehicle::factory()->create([
            'status' => 'available',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'category' => 'economy',
        ]);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Dacia', 'model' => 'Logan']);

        $response = $this->getJson(route('search.suggestions', ['q' => 'toy']));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $toyota->id)
            ->assertJsonPath('0.make', 'Toyota')
            ->assertJsonPath('0.model', 'Corolla')
            ->assertJsonPath('0.category', 'economy')
            ->assertJsonPath('0.imageUrl', null);
    }

    public function test_suggestions_matches_partial_and_case_insensitively(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Toyota', 'model' => 'Corolla']);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Renault', 'model' => 'Clio']);

        // Partial ("Cor") and upper-case ("TOYOTA") both match — SQLite LIKE is
        // ASCII case-insensitive, Postgres uses ILIKE in the live driver.
        $this->getJson(route('search.suggestions', ['q' => 'Cor']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.model', 'Corolla');

        $this->getJson(route('search.suggestions', ['q' => 'TOYOTA']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.make', 'Toyota');
    }

    public function test_suggestions_excludes_non_available_vehicles(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Toyota', 'model' => 'Corolla']);
        Vehicle::factory()->create(['status' => 'maintenance', 'make' => 'Toyota', 'model' => 'Land Cruiser']);
        Vehicle::factory()->create(['status' => 'rented', 'make' => 'Toyota', 'model' => 'RAV4']);

        $this->getJson(route('search.suggestions', ['q' => 'toyota']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.model', 'Corolla');
    }

    public function test_suggestions_requires_at_least_two_characters(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Toyota', 'model' => 'Corolla']);

        $this->getJson(route('search.suggestions', ['q' => 't']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_suggestions_without_a_query_returns_empty(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Toyota', 'model' => 'Corolla']);

        $this->getJson(route('search.suggestions'))->assertOk()->assertJsonCount(0);
        $this->getJson(route('search.suggestions', ['q' => '']))->assertOk()->assertJsonCount(0);
    }

    public function test_suggestions_limits_results_to_five(): void
    {
        Vehicle::factory()->count(7)->create(['status' => 'available', 'make' => 'Toyota']);

        $response = $this->getJson(route('search.suggestions', ['q' => 'toyota']));

        $response->assertOk();
        $this->assertCount(5, $response->json());
    }
}
