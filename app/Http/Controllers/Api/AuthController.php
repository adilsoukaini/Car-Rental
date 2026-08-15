<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Token-authenticated auth for the mobile app (Sanctum). Completely separate
 * from Breeze's session-based controllers — those log a browser session in
 * with cookies/CSRF; this issues a Bearer token the mobile app stores in
 * expo-secure-store and sends on every request.
 *
 * The register flow mirrors the web RegisteredUserController::store (fires the
 * Registered event for the verification email, queues the WelcomeEmail), with
 * both wrapped in try/catch so an unconfigured mailer can never block auth
 * (the same "core must not hard-crash over one optional thing" principle as
 * StripeGateway's lazy client and the driver-verification middleware guard).
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make((string) $request->input('password')),
        ]);

        try {
            event(new Registered($user));
        } catch (\Throwable) {
            // Silently continue — email verification can be resent later.
            // An unconfigured mailer must not block registration.
        }

        try {
            Mail::to($user->email)->queue(new WelcomeEmail($user));
        } catch (\Throwable) {
            // Silently continue — welcome email is nice-to-have, not critical.
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Delete the authenticated user's account and all associated personal data.
     *
     * Mirrors the web ProfileController::destroy semantics (password re-entry
     * required) adapted for token auth: revoke every Sanctum token, then delete
     * the user. Related data is cleaned up by the schema's FK cascade — reviews,
     * driver verifications, notifications, push tokens and damage reports are
     * `cascadeOnDelete`; bookings are `nullOnDelete` so the financial record is
     * retained (anonymised to a null `user_id`) while the personal data it
     * referenced is removed. Wrapped in a transaction so a partial failure can
     * never leave a half-deleted account.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            // Revoke all of the user's tokens — not just the current one — the
            // account itself is going away.
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Account deleted.'], 200);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
