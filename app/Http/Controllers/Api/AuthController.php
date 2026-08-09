<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
