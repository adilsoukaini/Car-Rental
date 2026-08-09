<?php

declare(strict_types=1);

namespace Plugins\DriverVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DriverVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverVerificationController extends Controller
{
    public function show(Request $request): Response|JsonResponse
    {
        $latest = $request->user()
            ->driverVerifications()
            ->latest('id')
            ->first();

        // Mobile app: GET /api/account/driver-verification returns the same
        // `{ verification }` shape the web page receives, as JSON.
        if ($request->is('api/*')) {
            return response()->json(['verification' => $latest]);
        }

        return Inertia::render('DriverVerification/Show', [
            'verification' => $latest,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $existing = $user->driverVerifications()
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        abort_if($existing, 422, 'A pending or already-approved verification exists.');

        $validated = $request->validate([
            'license_number' => ['required', 'string', 'max:255'],
            'license_country' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:-16 years'],
            'license_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('license_document')->store('driver-licenses', 'local');

        $verification = DriverVerification::create([
            'user_id' => $user->id,
            'license_number' => $validated['license_number'],
            'license_country' => $validated['license_country'],
            'date_of_birth' => $validated['date_of_birth'],
            'license_document_path' => $path,
            'status' => 'pending',
        ]);

        // Mobile app: return the created verification as JSON (the shape the
        // mobile lib/api.ts submit() documents). Web: redirect as before.
        if ($request->is('api/*')) {
            return response()->json($verification->fresh());
        }

        return redirect()->route('driver-verification.show');
    }
}
