<?php

declare(strict_types=1);

namespace Plugins\Reviews\Http\Controllers;

use App\Core\Events\ReviewSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Plugins\Reviews\Services\VerifiedRentalChecker;

class ReviewController extends Controller
{
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // A pre-check, not just relying on catching the DB's own unique
        // constraint violation: on Postgres, a failed statement aborts the
        // entire current transaction (not just that one statement, unlike
        // SQLite's looser behavior) — harmless for a real request (which
        // doesn't run inside an ambient outer transaction), but avoiding
        // the exception-driven path entirely is the more portable pattern
        // regardless of database engine. The catch below stays as a
        // defensive fallback for the genuine (rare, low-stakes) race
        // between this check and the insert.
        $alreadyReviewed = Review::where('vehicle_id', $vehicle->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyReviewed) {
            return back()->withErrors(['review' => 'You have already reviewed this vehicle.']);
        }

        try {
            $review = Review::create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $request->user()->id,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'is_verified_rental' => app(VerifiedRentalChecker::class)->check($request->user(), $vehicle),
                'is_approved' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['review' => 'You have already reviewed this vehicle.']);
        }

        ReviewSubmitted::dispatch($review);

        return back()->with('success', 'Review submitted — pending approval.');
    }
}
