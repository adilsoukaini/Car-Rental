<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Pipes;

use App\Models\Vehicle;
use Closure;
use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;
use Plugins\VehicleAttributes\Models\VehicleAttributeValue;
use Plugins\VehicleAttributes\Services\AttributeValueCaster;

/**
 * Registered on vehicle.attributes. $vehicle is injected via
 * FilterRegistry::applyWithContext() at call time — neither the fleet
 * plugin nor core references this plugin's classes (Hard Rule 1).
 *
 * Only definitions that actually carry a non-blank value on the vehicle
 * are returned, ordered by the definition's sort_order.
 */
class GetVehicleAttributesPipe
{
    public function __construct(private readonly Vehicle $vehicle) {}

    /**
     * @param  array<mixed>  $attributes
     * @return array<mixed>
     */
    public function handle(array $attributes, Closure $next): array
    {
        $valuesByDefinition = VehicleAttributeValue::where('vehicle_id', $this->vehicle->id)
            ->with('definition')
            ->get()
            ->keyBy('attribute_definition_id');

        $resolved = VehicleAttributeDefinition::orderBy('sort_order')
            ->get()
            ->map(static function (VehicleAttributeDefinition $def) use ($valuesByDefinition): ?array {
                $valueModel = $valuesByDefinition->get($def->id);
                $cast = AttributeValueCaster::cast($def, $valueModel?->value);

                if ($cast === null) {
                    return null;
                }

                return [
                    'key' => $def->key,
                    'label' => $def->name,
                    'type' => $def->type,
                    'value' => $cast,
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        return $next($resolved);
    }
}
