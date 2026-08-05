<?php

namespace App\Http\Middleware;

use App\Core\Support\ThemeManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Full resolved theme data — ThemeManager returns the active DB
            // row's data, or the hardcoded default if no DB row is active
            // yet. app.tsx applies this via ThemeProvider as CSS variable
            // overrides at runtime, enabling DB-driven theme changes with
            // zero rebuild.
            'themeData' => ThemeManager::resolveActive(),
        ];
    }
}
