<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        // The atomic JSON path update (`metadata->currency`) compiles to
        // jsonb_set(metadata, '{currency}', ...) on Postgres, which returns
        // NULL when the column itself is NULL — so a first-time save on a
        // fresh user (metadata = NULL) would silently write nothing. Seed the
        // object once with a full write so the atomic path update has a
        // target; subsequent saves keep using the race-safe atomic path
        // update (never a read-modify-write, so concurrent preference saves
        // can't lose each other's keys).
        //
        // Both updates go through the query builder, not $user->update():
        // the model's mass-assignment guard (fillableFromArray) matches keys
        // literally, so the `metadata->currency` JSON-path key would be
        // silently discarded there.
        $query = User::query()->whereKey($user->getKey());

        if ($user->metadata === null) {
            $query->update([
                'metadata' => ['currency' => $validated['currency']],
            ]);
        } else {
            $query->update([
                'metadata->currency' => $validated['currency'],
            ]);
        }

        return response()->noContent();
    }
}
