<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string $type
 * @property array<string|int, string>|list<string>|null $options
 * @property int $sort_order
 * @property Collection<int, VehicleAttributeValue> $values
 */
class VehicleAttributeDefinition extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'key', 'type', 'options', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<VehicleAttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(VehicleAttributeValue::class, 'attribute_definition_id');
    }
}
