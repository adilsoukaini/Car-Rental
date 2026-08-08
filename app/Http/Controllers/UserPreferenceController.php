<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Persists lightweight, per-user display preferences.
 *
 * Currently only the storefront display currency (a guest keeps it in
 * localStorage; a logged-in user's choice is stored here so it survives
 * across sessions/devices — same split as locale vs the ?lang= param, but
 * currency is not in the URL). Values live in the users.metadata JSON column
 * (rule 6 — a small addition to a core model, no schema change).
 */
class UserPreferenceController extends Controller
{
    /**
     * Save the authenticated user's preferred display currency.
     * Returns 204 so the Inertia client can fire-and-forget the request
     * (the frontend already applied the new currency optimistically).
     */
    public function updateCurrency(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(['MAD', 'EUR', 'USD'])],
        ]);

        $user = $request->user();
        $metadata = $user->metadata ?? [];
        $metadata['currency'] = $validated['currency'];
        $user->metadata = $metadata;
        $user->save();

        return response()->noContent();
    }
}
