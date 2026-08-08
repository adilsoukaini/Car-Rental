<?php

namespace Tests\Feature;

use App\Models\HomepageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the admin-editable homepage content: the singleton model helper and
 * the `/` route sharing it to the storefront Home page (Hard Rule 11 — an
 * admin control must actually change what visitors see; this is the
 * automated half of that proof, the real browser round-trip is verified
 * manually via Playwright).
 */
class HomepageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_creates_the_singleton_row_with_defaults(): void
    {
        $content = HomepageContent::current();

        $this->assertSame(1, $content->id);
        $this->assertSame("L'excellence de la location de voitures.", $content->hero_title);
        $this->assertSame('Pourquoi choisir '.config('app.name', 'Location de voitures').' ?', $content->features_title);
        $this->assertSame("Prêt pour l'aventure ?", $content->cta_band_title);

        // Exactly one row, always.
        $this->assertSame(1, HomepageContent::count());
    }

    public function test_current_returns_the_existing_row_without_overwriting(): void
    {
        $content = HomepageContent::current();
        $content->update(['hero_title' => 'Edited title']);

        $again = HomepageContent::current();

        $this->assertSame(1, $again->id);
        $this->assertSame('Edited title', $again->hero_title);
        $this->assertSame(1, HomepageContent::count());
    }

    public function test_home_route_shares_homepage_content_props(): void
    {
        $content = HomepageContent::current();
        $content->update([
            'hero_title' => 'Admin title',
            'hero_subtitle' => 'Admin subtitle',
            'cta_band_title' => 'Admin CTA band',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('homepageContent.hero_title', 'Admin title')
            ->where('homepageContent.hero_subtitle', 'Admin subtitle')
            ->where('homepageContent.cta_band_title', 'Admin CTA band')
            ->where('homepageContent.features_title', 'Pourquoi choisir '.config('app.name', 'Location de voitures').' ?'));
    }
}
