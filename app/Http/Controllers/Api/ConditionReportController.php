<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Events\DamageReported;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DamageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API condition-report endpoint for the mobile app — the photo check-in /
 * check-out ("état des lieux") feature.
 *
 * This is the mobile equivalent of the web panel's "Report Condition" action
 * on ViewBooking: it accepts multipart form data (stage, optional description,
 * up to 6 photos), persists a DamageReport, and dispatches the same
 * DamageReported event the panel fires. Photos are stored on the private
 * `local` disk under `damage-reports`, matching the panel's FileUpload config.
 *
 * Ownership mirrors Api\BookingController::show — only the authenticated user
 * who made the booking may file a condition report against it (guests who
 * booked without an account have user_id null and are rejected, just as they
 * cannot GET the booking by id).
 */
class ConditionReportController extends Controller
{
    public function store(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $booking->user_id === $user->id, 403);

        $validated = $request->validate([
            'stage' => ['required', 'in:pickup,return'],
            'description' => ['nullable', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:10240'],
        ]);

        $photoPaths = [];
        foreach ($request->file('photos') ?? [] as $file) {
            $photoPaths[] = $file->store('damage-reports', 'local');
        }

        $report = DamageReport::create([
            'booking_id' => $booking->id,
            'stage' => $validated['stage'],
            'description' => $validated['description'] ?? '',
            'photo_paths' => $photoPaths,
            'reported_by' => $user->id,
        ]);

        DamageReported::dispatch($booking, $report->stage, $report->description, $report->photo_paths ?? []);

        return response()->json($report, 201);
    }
}
