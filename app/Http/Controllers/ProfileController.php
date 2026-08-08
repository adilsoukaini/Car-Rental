<?php

namespace App\Http\Controllers;

use App\Core\Support\SlotRegistry;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Booking;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $recentBookings = Booking::where('user_id', $request->user()->id)
            ->with(['vehicle:id,make,model,year', 'pickupLocation:id,name,city', 'returnLocation:id,name,city'])
            ->latest()
            ->limit(5)
            ->get(['id', 'vehicle_id', 'pickup_location_id', 'return_location_id', 'status', 'pickup_at', 'return_at', 'total_price', 'security_deposit_amount', 'created_at']);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'dashboardWidgets' => SlotRegistry::render('account.dashboardWidgets', [
                'recentBookings' => $recentBookings,
            ]),
            // The profile page now hosts the driver-verification status card
            // (verification management moved out of the public navbar). The
            // latest DriverVerification row is passed for the status card's
            // detail (rejection_reason etc.); the shared `driverVerificationStatus`
            // prop (guarded in HandleInertiaRequests) stays the authority for
            // the status string. Guarded on the plugin-owned table existing —
            // same "core middleware/controller must not hard-crash the whole
            // site over one optional feature" lesson as HandleInertiaRequests.
            'driverVerification' => Schema::hasTable('driver_verifications')
                ? $request->user()->driverVerifications()->latest('id')->first()
                : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
