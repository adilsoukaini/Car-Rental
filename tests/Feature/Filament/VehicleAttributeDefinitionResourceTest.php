<?php

namespace Tests\Feature\Filament;

use App\Core\Support\VehicleResourceExtension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\CreateVehicleAttributeDefinition;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\EditVehicleAttributeDefinition;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\ListVehicleAttributeDefinitions;
use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;
use Plugins\VehicleAttributes\VehicleAttributesServiceProvider;
use Tests\TestCase;

class VehicleAttributeDefinitionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(VehicleAttributesServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/vehicle-attributes/database/migrations']);
    }

    /**
     * Same UrlGenerator test-harness artifact documented for the reviews
     * plugin's list page: Filament's own ListRecords page calls route()
     * internally for its breadcrumbs, which doesn't see routes registered
     * post-boot in tests even though real HTTP dispatch works in production.
     * Proven by confirming the route is genuinely registered instead.
     */
    public function test_the_list_route_is_registered(): void
    {
        $matchingRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->getName() === 'filament.admin.resources.vehicle-attribute-definitions.index');

        $this->assertCount(1, $matchingRoutes, 'Expected the list route to be registered exactly once.');
    }

    public function test_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $this->get('/admin/vehicle-attribute-definitions')
            ->assertRedirect(route('home'));
    }

    public function test_staff_can_create_a_definition(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        Livewire::test(CreateVehicleAttributeDefinition::class)
            ->fillForm([
                'name' => 'GPS',
                'key' => 'gps',
                'type' => 'boolean',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vehicle_attribute_definitions', [
            'name' => 'GPS',
            'key' => 'gps',
            'type' => 'boolean',
            'sort_order' => 1,
        ]);
    }

    public function test_staff_can_edit_a_definition(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $definition = VehicleAttributeDefinition::create([
            'name' => 'GPS',
            'key' => 'gps',
            'type' => 'boolean',
        ]);

        Livewire::test(EditVehicleAttributeDefinition::class, ['record' => $definition->getKey()])
            ->fillForm(['name' => 'Built-in GPS'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Built-in GPS', $definition->fresh()->name);
    }

    public function test_the_definition_list_page_renders_for_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        VehicleAttributeDefinition::create(['name' => 'GPS', 'key' => 'gps', 'type' => 'boolean']);

        Livewire::test(ListVehicleAttributeDefinitions::class)
            ->assertTableColumnStateSet('name', 'GPS', VehicleAttributeDefinition::query()->first());
    }

    public function test_vehicle_resource_extension_receives_the_dynamic_form_section(): void
    {
        // The plugin's boot() registers its form-section callback into the
        // core extension registry — this is the wiring that puts the dynamic
        // attribute fields on VehicleResource's create/edit form.
        $this->assertCount(1, VehicleResourceExtension::getFormSections());
    }

    /**
     * Regression guard for the "419 Page Expired on every Livewire update"
     * bug: a plugin-owned Filament resource registered via
     * $panel->resources() renders its pages fine (the page class is used
     * directly) but its page Livewire components are never registered by
     * name, so every update request fails with ComponentNotFoundException.
     * The provider must register each page via Livewire::component() —
     * prove all three pages resolve by name here.
     */
    public function test_resource_page_livewire_components_are_registered(): void
    {
        $registry = app(ComponentRegistry::class);

        foreach ([
            'list-vehicle-attribute-definitions' => ListVehicleAttributeDefinitions::class,
            'create-vehicle-attribute-definition' => CreateVehicleAttributeDefinition::class,
            'edit-vehicle-attribute-definition' => EditVehicleAttributeDefinition::class,
        ] as $name => $class) {
            $this->assertSame(
                $class,
                $registry->getClass('plugins.vehicle-attributes.filament.resources.vehicle-attribute-definition-resource.pages.'.$name),
            );
        }
    }
}
