<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Models;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property int $attribute_definition_id
 * @property string|null $value
 * @property VehicleAttributeDefinition $definition
 * @property Vehicle $vehicle
 */
class VehicleAttributeValue extends Model
{
    /** @var list<string> */
    protected $fillable = ['vehicle_id', 'attribute_definition_id', 'value'];

    /** @return BelongsTo<VehicleAttributeDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(VehicleAttributeDefinition::class, 'attribute_definition_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
