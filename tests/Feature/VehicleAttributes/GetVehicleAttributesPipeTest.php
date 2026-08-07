<?php

namespace Tests\Feature\VehicleAttributes;

use App\Core\Support\FilterRegistry;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;
use Plugins\VehicleAttributes\Models\VehicleAttributeValue;
use Plugins\VehicleAttributes\VehicleAttributesServiceProvider;
use Tests\TestCase;

class GetVehicleAttributesPipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(VehicleAttributesServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/vehicle-attributes/database/migrations']);
    }

    /** @return list<array<string, mixed>> */
    private function fetch(Vehicle $vehicle): array
    {
        return FilterRegistry::applyWithContext(
            'vehicle.attributes',
            [],
            [Vehicle::class => $vehicle],
        );
    }

    public function test_only_definitions_with_real_values_are_returned_in_sort_order(): void
    {
        $vehicle = Vehicle::factory()->create();

        $gps = VehicleAttributeDefinition::create(['name' => 'GPS', 'key' => 'gps', 'type' => 'boolean', 'sort_order' => 2]);
        $insurance = VehicleAttributeDefinition::create(['name' => 'Insurance', 'key' => 'insurance_type', 'type' => 'select', 'sort_order' => 1]);
        // No value row — must not appear.
        VehicleAttributeDefinition::create(['name' => 'Unset', 'key' => 'unset', 'type' => 'text', 'sort_order' => 3]);

        VehicleAttributeValue::create(['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $gps->id, 'value' => '1']);
        VehicleAttributeValue::create(['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $insurance->id, 'value' => 'full']);

        $result = $this->fetch($vehicle);

        $this->assertCount(2, $result);
        $this->assertSame('insurance_type', $result[0]['key']);
        $this->assertSame('gps', $result[1]['key']);
    }

    public function test_values_are_cast_per_type_for_the_frontend(): void
    {
        $vehicle = Vehicle::factory()->create();

        $gps = VehicleAttributeDefinition::create(['name' => 'GPS', 'key' => 'gps', 'type' => 'boolean']);
        $mileageLimit = VehicleAttributeDefinition::create(['name' => 'Mileage Limit', 'key' => 'mileage_limit', 'type' => 'number']);
        $insurance = VehicleAttributeDefinition::create([
            'name' => 'Insurance', 'key' => 'insurance_type', 'type' => 'select',
            'options' => ['full' => 'Full Coverage', 'basic' => 'Basic'],
        ]);

        VehicleAttributeValue::create(['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $gps->id, 'value' => '1']);
        VehicleAttributeValue::create(['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $mileageLimit->id, 'value' => '150']);
        VehicleAttributeValue::create(['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $insurance->id, 'value' => 'full']);

        $result = collect($this->fetch($vehicle))->keyBy('key');

        $this->assertSame('GPS', $result['gps']['label']);
        $this->assertTrue($result['gps']['value']);
        $this->assertSame(150, $result['mileage_limit']['value']);
        $this->assertSame('Full Coverage', $result['insurance_type']['value']);
    }

    public function test_values_from_another_vehicle_are_excluded(): void
    {
        $vehicle = Vehicle::factory()->create();
        $other = Vehicle::factory()->create();

        $gps = VehicleAttributeDefinition::create(['name' => 'GPS', 'key' => 'gps', 'type' => 'boolean']);
        VehicleAttributeValue::create(['vehicle_id' => $other->id, 'attribute_definition_id' => $gps->id, 'value' => '1']);

        $this->assertSame([], $this->fetch($vehicle));
    }

    public function test_blank_values_are_dropped(): void
    {
        $vehicle = Vehicle::factory()->create();

        $gps = VehicleAttributeDefinition::create(['name' => 'GPS', 'key' => 'gps', 'type' => 'boolean']);
        VehicleAttributeValue::create(['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $gps->id, 'value' => null]);

        $this->assertSame([], $this->fetch($vehicle));
    }

    public function test_a_vehicle_with_no_values_returns_an_empty_array(): void
    {
        Vehicle::factory()->create();

        $this->assertSame([], $this->fetch(Vehicle::factory()->create()));
    }
}
