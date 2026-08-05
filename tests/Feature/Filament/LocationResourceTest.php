<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_location_through_the_admin_resource(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => 'Marrakech Menara Airport',
                'address_line' => 'Route de l\'Aéroport',
                'city' => 'Marrakech',
                'country' => 'Morocco',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('locations', [
            'name' => 'Marrakech Menara Airport',
            'city' => 'Marrakech',
        ]);
    }

    public function test_staff_can_deactivate_a_location_through_the_admin_resource(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $location = Location::factory()->create(['is_active' => true]);

        Livewire::test(EditLocation::class, ['record' => $location->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse((bool) $location->fresh()->is_active);
    }

    public function test_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $response = $this->get('/admin/locations');

        $response->assertRedirect(route('home'));
    }
}
