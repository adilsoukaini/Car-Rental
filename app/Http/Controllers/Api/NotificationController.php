<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Return the 50 most recent notifications for the authenticated user. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $guestEmail = $user ? null : session('guest_email');

        $notifications = Notification::forRecipient($user?->id, $guestEmail, 50);

        return response()->json($notifications);
    }

    /** Get unread count for badge display. */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $guestEmail = $user ? null : session('guest_email');

        return response()->json([
            'count' => Notification::unreadCount($user?->id, $guestEmail),
        ]);
    }

    /** Mark a single notification as read. */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();
        // Only the notification's owner may mark it as read.
        if ($notification->user_id !== null && $notification->user_id !== (int) $user?->id) {
            abort(403);
        }
        $notification->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    /** Mark all as read. */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            Notification::where('user_id', $user->id)->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
