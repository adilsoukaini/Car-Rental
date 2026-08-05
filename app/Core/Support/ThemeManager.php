<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Models\Theme;
use Illuminate\Support\Facades\DB;

class ThemeManager
{
    /**
     * The built-in default theme data, resolved from resources/theme/clients/default.ts
     * at the time of writing. This is the single source of truth for the fallback
     * value and for the seeded "Default" DB row — having one PHP constant prevents
     * the fallback and the seed from drifting apart.
     *
     * Fields match the Semantic interface defined in resources/theme/semantic.ts.
     *
     * @return array<string, mixed>
     */
    public static function defaultData(): array
    {
        return [
            'color' => [
                'primary' => '#2563eb',  // p.color.blue[600]
                'primaryHover' => '#3b82f6',  // p.color.blue[500]
                'onPrimary' => '#ffffff',  // p.color.white
                'secondary' => '#f59e0b',  // p.color.amber[500]
                'onSecondary' => '#18181b',  // p.color.gray[900]
                'background' => '#fafafa',  // p.color.gray[50]
                'surface' => '#ffffff',  // p.color.white
                'surfaceRaised' => '#ffffff', // p.color.white
                'text' => '#18181b',  // p.color.gray[900]
                'textMuted' => '#52525b',  // p.color.gray[600]
                'border' => '#d4d4d8',  // p.color.gray[300]
                'success' => '#16a34a',  // p.color.green[600]
                'danger' => '#dc2626',  // p.color.red[600]
                'warning' => '#d97706',  // p.color.amber[600]
                'focusRing' => '#3b82f6',  // p.color.blue[500]
                'onPhoto' => '#ffffff',  // near-white — readable on any dark photo scrim
                'photoScrim' => '#000000', // pure black — used at opacity as gradient scrim
            ],
            'font' => [
                'display' => '"Space Grotesk", sans-serif',
                'body' => '"Inter", sans-serif',
                'mono' => '"JetBrains Mono", monospace',
            ],
            'radius' => [
                'interactive' => '8px',    // p.radius.md
                'container' => '16px',   // p.radius.lg
                'pill' => '9999px', // p.radius.full
            ],
            'shadow' => [
                'resting' => '0 1px 2px rgba(0,0,0,0.05)',  // p.shadow.sm
                'raised' => '0 4px 6px rgba(0,0,0,0.1)',   // p.shadow.md
                'overlay' => '0 10px 15px rgba(0,0,0,0.1)', // p.shadow.lg
            ],
        ];
    }

    /**
     * Activate a theme by ID. Wraps the swap in a transaction so there is never
     * a moment where zero rows or two rows are active simultaneously.
     */
    public static function activate(int $themeId): void
    {
        DB::transaction(function () use ($themeId) {
            Theme::query()->update(['is_active' => false]);
            Theme::findOrFail($themeId)->update(['is_active' => true]);
        });
    }

    /**
     * Return the resolved theme data array for the current request.
     *
     * Priority: active DB row → hardcoded default (fresh install, no seeding yet).
     *
     * @return array<string, mixed>
     */
    public static function resolveActive(): array
    {
        $theme = Theme::where('is_active', true)->first();

        return $theme ? $theme->data : static::defaultData();
    }
}
