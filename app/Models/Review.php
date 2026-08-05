<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A core model per the same precedent as DriverVerification: the
 * reviews plugin owns the migration, business logic, and Filament
 * resource, but the model itself lives in App\Models so core (and any
 * future plugin) can reference it without a core class ever importing
 * from the plugin's namespace (Hard Rule 1).
 *
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property bool $is_verified_rental
 * @property bool $is_approved
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Vehicle $vehicle
 */
#[Fillable(['vehicle_id', 'user_id', 'rating', 'title', 'body', 'is_verified_rental', 'is_approved'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified_rental' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
