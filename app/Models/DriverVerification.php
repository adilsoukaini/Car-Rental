<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Carbon $date_of_birth
 * @property string $status pending|approved|rejected
 */
#[Fillable([
    'user_id', 'license_number', 'license_country', 'date_of_birth',
    'license_document_path', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
])]
class DriverVerification extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Age as of a given date — eligibility is evaluated against the
     * booking's pickup date, not "today", so a driver who turns 21 the
     * week after booking but before pickup is correctly eligible.
     */
    public function ageAt(CarbonInterface $date): int
    {
        return (int) $this->date_of_birth->diffInYears($date);
    }
}
