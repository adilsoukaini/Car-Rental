<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_vehicle_through_the_admin_resource(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'make' => 'Toyota',
                'model' => 'Yaris',
                'year' => 2025,
                'category' => 'economy',
                'license_plate' => '99999-Z-9',
                'daily_rate' => 300,
                'seat_count' => 5,
                'transmission_type' => 'automatic',
                'fuel_type' => 'petrol',
                'mileage' => 100,
                'status' => 'available',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vehicles', [
            'make' => 'Toyota',
            'model' => 'Yaris',
            'license_plate' => '99999-Z-9',
        ]);
    }

    public function test_staff_can_edit_an_existing_vehicle_through_the_admin_resource(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $vehicle = Vehicle::factory()->create(['daily_rate' => 300]);

        Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->fillForm(['daily_rate' => 550])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('550.00', $vehicle->fresh()->daily_rate);
    }

    public function test_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $response = $this->get('/admin/vehicles');

        $response->assertRedirect(route('home'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/vehicles');

        $response->assertRedirect('/admin/login');
    }
}
