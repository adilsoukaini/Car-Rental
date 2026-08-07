<?php

namespace App\Http\Middleware;

use App\Core\Support\ThemeManager;
use App\Models\DriverVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            // Full resolved theme data — ThemeManager returns the active DB
            // row's data, or the hardcoded default if no DB row is active
            // yet. app.tsx applies this via ThemeProvider as CSS variable
            // overrides at runtime, enabling DB-driven theme changes with
            // zero rebuild.
            'themeData' => ThemeManager::resolveActive(),
            // Drives the storefront header's conditional "Driver
            // verification" link (null for guests — verification is
            // per-User, exempt for guests, same precedent as everywhere
            // else this project treats driver verification). No Hard
            // Rule 1 concern: DriverVerification is a core model (Phase 9
            // precedent), so core querying it directly here is fine —
            // no plugin namespace involved. The `driver_verifications`
            // TABLE, however, is owned and migrated by the
            // driver-verification plugin, not core — unlike `themes`
            // (a core migration, always present under RefreshDatabase),
            // this table may genuinely not exist (plugin disabled, or its
            // migration hasn't run yet). Guarding with Schema::hasTable()
            // and degrading to null is the same "core middleware must not
            // hard-crash the entire site over one optional feature" lesson
            // as StripeGateway's lazy-client fix in Phase 7 — found here by
            // the full test suite breaking across every authenticated
            // route the moment this table was queried unconditionally.
            'driverVerificationStatus' => ($user instanceof User && Schema::hasTable('driver_verifications'))
                ? $this->latestDriverVerificationStatus($user)
                : null,
            // Site identity — drives the storefront SiteLogo component's
            // name/logo. siteName falls back to config('app.name'); logoUrl
            // comes from config/site.php (null when not configured, in which
            // case SiteLogo renders its default icon mark).
            'siteIdentity' => [
                'siteName' => config('app.name', 'Car Rental'),
                'logoUrl' => config('site.logo_url', null),
            ],
            // Any flash message set on the session ({ message, type } | null)
            // — consumed by the ToastContainer component in the root layout.
            'flash' => fn () => $request->session()->get('flash'),
        ];
    }

    /**
     * $request->user() erases to the generic Authenticatable contract, so
     * the instanceof User narrowing above is required before this call is
     * type-safe — same "real narrowing, not a suppression" precedent as
     * ViewBooking's $this->record handling (see CLAUDE.md).
     */
    private function latestDriverVerificationStatus(User $user): string
    {
        $latest = $user->driverVerifications()->latest('id')->first();

        return $latest instanceof DriverVerification ? $latest->status : 'none';
    }
}
