<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Filament\Forms;

use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;
use Plugins\VehicleAttributes\Models\VehicleAttributeValue;
use Plugins\VehicleAttributes\Services\AttributeValueCaster;

/**
 * A dynamically-built form section on VehicleResource's create/edit form.
 *
 * For every active VehicleAttributeDefinition it renders a typed field
 * (TextInput for text/number, Textarea, Toggle for boolean, Select with
 * options), all living under the form state key `attributes` so they never
 * collide with real Vehicle columns (which are not fillable, so the
 * `attributes` array is ignored by Vehicle::update()/create()).
 *
 * Loading and saving happen through Filament's relationship lifecycle:
 *  - loadStateFromRelationshipsUsing() fills each field from the vehicle's
 *    existing VehicleAttributeValue rows when the edit form hydrates.
 *  - saveRelationshipsUsing() runs after the vehicle record is persisted
 *    (CreateRecord/EditRecord call Schema::saveRelationships() via
 *    getState()), upserting one value row per definition.
 *
 * A blank value (and a boolean switched off) deletes the row rather than
 * persisting '0' — the detail page only shows attributes a vehicle
 * actually carries.
 */
class VehicleAttributesFormSection extends Section
{
    /** @var array<string, VehicleAttributeDefinition> keyed by definition key */
    public array $definitionsByKey = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->heading('Custom Attributes');
        $this->description('Specs defined by the Vehicle Attributes plugin.');
        $this->statePath('attributes');
        $this->schema(fn (): array => $this->buildFields());

        $this->loadStateFromRelationshipsUsing(function (Section $component): void {
            $vehicle = $component->getRecord();
            if (! $vehicle instanceof Vehicle || ! $vehicle->exists) {
                return;
            }

            $valuesByKey = VehicleAttributeValue::where('vehicle_id', $vehicle->id)
                ->with('definition')
                ->get()
                ->keyBy(fn (VehicleAttributeValue $v): string => $v->definition->key);

            foreach ($this->definitionsByKey as $key => $definition) {
                $field = $this->findField($component, $key);

                if ($field !== null) {
                    $field->state(AttributeValueCaster::cast($definition, $valuesByKey->get($key)?->value));
                }
            }
        });

        $this->saveRelationshipsUsing(function (Section $component): void {
            $vehicle = $component->getRecord();
            if (! $vehicle instanceof Vehicle || ! $vehicle->exists) {
                return;
            }

            $state = $component->getState() ?? [];

            foreach ($this->definitionsByKey as $key => $definition) {
                $value = $state[$key] ?? null;

                if ($value === null || $value === '') {
                    VehicleAttributeValue::where('vehicle_id', $vehicle->id)
                        ->where('attribute_definition_id', $definition->id)
                        ->delete();

                    continue;
                }

                $stored = match ($definition->type) {
                    'boolean' => $value ? '1' : '0',
                    default => (string) $value,
                };

                VehicleAttributeValue::updateOrCreate(
                    ['vehicle_id' => $vehicle->id, 'attribute_definition_id' => $definition->id],
                    ['value' => $stored],
                );
            }
        });
    }

    /**
     * @return array<int, Component>
     */
    protected function buildFields(): array
    {
        $fields = [];

        foreach (VehicleAttributeDefinition::orderBy('sort_order')->get() as $definition) {
            $this->definitionsByKey[$definition->key] = $definition;
            $fields[] = $this->fieldFor($definition);
        }

        return $fields;
    }

    protected function fieldFor(VehicleAttributeDefinition $definition): Component
    {
        $name = $definition->key;

        return match ($definition->type) {
            'number' => TextInput::make($name)->numeric()->label($definition->name),
            'textarea' => Textarea::make($name)->label($definition->name),
            'boolean' => Toggle::make($name)->label($definition->name),
            'select' => Select::make($name)
                ->label($definition->name)
                ->options($this->selectOptions($definition))
                ->nullable(),
            default => TextInput::make($name)->label($definition->name),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function selectOptions(VehicleAttributeDefinition $definition): array
    {
        $options = $definition->options ?? [];

        // Simple list: ["full", "basic"] -> stored value == label
        if (array_is_list($options)) {
            return array_combine($options, $options);
        }

        // Associative map: {"full": "Full", "basic": "Basic"}
        return array_map('strval', $options);
    }

    protected function findField(Section $component, string $key): ?Component
    {
        foreach ($component->getChildComponents() as $field) {
            if ($field->getName() === $key) {
                return $field;
            }
        }

        return null;
    }
}
