<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DamageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Core model, same precedent as Review/DriverVerification: the
 * damage-reporting plugin owns only the migration; the model lives in
 * App\Models so core's ViewBooking can create/query it directly without
 * ever importing the plugin's namespace (Hard Rule 1).
 *
 * @property string $stage 'pickup'|'return'
 * @property string $description
 * @property list<string>|null $photo_paths
 * @property Carbon $created_at
 * @property-read Booking $booking
 * @property-read User $reportedBy
 */
#[Fillable(['booking_id', 'stage', 'description', 'photo_paths', 'reported_by'])]
class DamageReport extends Model
{
    /** @use HasFactory<DamageReportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
