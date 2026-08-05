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
