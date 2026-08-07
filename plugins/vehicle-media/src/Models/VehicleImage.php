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

    /**
     * Delete the physical file when the DB row goes away, so an admin
     * deleting a vehicle image doesn't orphan the file on the public disk.
     * Seeded demo images store a full remote URL (picsum.photos) in `path`
     * — those are skipped, only local files on the public disk are deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (VehicleImage $image): void {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    /**
     * Resolve a stored path to a usable URL.
     *
     * Seeded demo images store a full remote URL (picsum.photos) in `path`;
     * admin-uploaded images store a local path on the public disk. Full
     * http(s) URLs are returned as-is, everything else goes through
     * Storage::url() — single rule shared by the `url()` accessor and
     * GetVehicleGalleryPipe so both resolve the same way.
     */
    public static function resolveUrl(string $path): string
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : Storage::url($path);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => self::resolveUrl($this->path));
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
