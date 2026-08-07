<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CatalogControlSettings;
use App\Models\CatalogControlSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the admin CatalogControlSettings page (Hard Rule 11 — an admin
 * control must change what visitors see): the Admin-only access gate, the
 * toggle persistence to catalog_control_settings, and the cache reset so the
 * very next registry lookup reflects the saved state. The storefront half of
 * the round-trip (a disabled filter disappearing from /vehicles) is covered
 * in VehicleControllerTest; the real-browser walkthrough is manual.
 */
class CatalogControlSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_the_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get('/admin/catalog-controls')->assertOk();
    }

    public function test_staff_cannot_access_the_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        // Staff can enter the panel generally (User::canAccessPanel is Staff+),
        // but this Admin-only page's canAccess() gate returns 403 for them.
        $this->get('/admin/catalog-controls')->assertForbidden();
    }

    public function test_customer_cannot_access_the_page(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $this->get('/admin/catalog-controls')->assertRedirect(route('home'));
    }

    public function test_disabling_a_filter_persists_and_is_reflected_in_registry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        Livewire::test(CatalogControlSettings::class)
            ->fillForm(['filter_transmission' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('catalog_control_settings', [
            'control_type' => 'filter',
            'control_id' => 'transmission',
            'is_enabled' => false,
        ]);

        $this->assertFalse(CatalogControlSetting::isControlEnabled('filter', 'transmission'));
        $this->assertTrue(CatalogControlSetting::isControlEnabled('filter', 'category'));
    }

    public function test_re_enabling_a_filter_persists_and_is_reflected_in_registry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        CatalogControlSetting::updateOrCreate(
            ['control_type' => 'filter', 'control_id' => 'transmission'],
            ['is_enabled' => false],
        );
        CatalogControlSetting::resetCache();

        Livewire::test(CatalogControlSettings::class)
            ->fillForm(['filter_transmission' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('catalog_control_settings', [
            'control_type' => 'filter',
            'control_id' => 'transmission',
            'is_enabled' => true,
        ]);

        $this->assertTrue(CatalogControlSetting::isControlEnabled('filter', 'transmission'));
    }

    public function test_disabling_a_sort_persists_and_is_reflected_in_registry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        Livewire::test(CatalogControlSettings::class)
            ->fillForm(['sort_price_asc' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('catalog_control_settings', [
            'control_type' => 'sort',
            'control_id' => 'price_asc',
            'is_enabled' => false,
        ]);

        $this->assertFalse(CatalogControlSetting::isControlEnabled('sort', 'price_asc'));
        $this->assertTrue(CatalogControlSetting::isControlEnabled('sort', 'price_desc'));
    }
}
