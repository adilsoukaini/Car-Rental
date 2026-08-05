<?php

declare(strict_types=1);

namespace Plugins\DriverVerification\Filters;

use App\Core\Support\DriverEligibilityCheckRequest;
use App\Models\DriverVerification;
use Closure;

/**
 * Guest bookings (no user account) are exempt — enforcing verification for
 * guests would require either forcing account creation or a whole
 * pending-then-admin-reviews-then-confirms booking workflow that doesn't
 * exist yet. See CLAUDE.md's driver-verification section for the full
 * reasoning behind this scope call.
 *
 * A registered user booking a category with no configured minimum age is
 * always eligible. A category WITH a minimum age requires an `approved`
 * DriverVerification whose age at the booking's pickup date (not "today")
 * meets that minimum.
 */
class CoreDriverEligibilityCheckPipe
{
    public function handle(DriverEligibilityCheckRequest $request, Closure $next): mixed
    {
        if ($request->userId === null) {
            return $next($request);
        }

        $minimumAge = config("driver-verification.minimum_age_by_category.{$request->vehicleCategory}");

        if ($minimumAge === null) {
            return $next($request);
        }

        $verification = DriverVerification::where('user_id', $request->userId)
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->first();

        if ($verification === null || $verification->ageAt($request->pickupAt) < $minimumAge) {
            return false;
        }

        return $next($request);
    }
}
