<?php

declare(strict_types=1);

namespace Plugins\Reviews\Http\Controllers;

use App\Core\Events\ReviewSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Plugins\Reviews\Services\VerifiedRentalChecker;

class ReviewController extends Controller
{
    public function store(Request $request, Vehicle $vehicle): RedirectResponse|JsonResponse
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
            if ($request->is('api/*')) {
                return $this->alreadyReviewedJson();
            }

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
            if ($request->is('api/*')) {
                return $this->alreadyReviewedJson();
            }

            return back()->withErrors(['review' => 'You have already reviewed this vehicle.']);
        }

        ReviewSubmitted::dispatch($review);

        // Mobile app: POST /api/vehicles/{vehicle}/reviews returns the created
        // review in the exact shape GetVehicleReviewsPipe serializes (the shape
        // the mobile Review type expects), so a client can optimistically append
        // it to the detail screen's list. Web: redirect back as before.
        if ($request->is('api/*')) {
            return response()->json([
                'id' => $review->id,
                'authorName' => $review->user->name,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'isVerifiedRental' => $review->is_verified_rental,
                'createdAt' => $review->created_at->format('M j, Y'),
            ], 201);
        }

        return back()->with('success', 'Review submitted — pending approval.');
    }

    /**
     * The one-per-vehicle unique constraint fails — a duplicate submission. As
     * a standard Laravel 422 validation-error payload so API clients surface it
     * under the `review` field via the same validation-errors path as any other
     * failed field.
     */
    private function alreadyReviewedJson(): JsonResponse
    {
        return response()->json([
            'message' => 'You have already reviewed this vehicle.',
            'errors' => ['review' => ['You have already reviewed this vehicle.']],
        ], 422);
    }
}
