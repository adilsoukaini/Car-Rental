<?php

declare(strict_types=1);

namespace Plugins\VehicleMedia\Models;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property string $path
 * @property string|null $alt_text
 * @property int $sort_order
 * @property bool $is_primary
 */
class VehicleImage extends Model
{
    /** @var list<string> */
    protected $appends = ['url'];

    protected $fillable = [
        'vehicle_id',
        'path',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::url($this->path));
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Set this image as the primary for its vehicle.
     * Same transactional deactivate-then-activate pattern as
     * ThemeManager::activate() and the e-commerce project's ProductImage.
     */
    public function makePrimary(): void
    {
        DB::transaction(function () {
            static::where('vehicle_id', $this->vehicle_id)->update(['is_primary' => false]);
            $this->update(['is_primary' => true]);
        });
    }
}
