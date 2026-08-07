<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Models\Theme;
use Illuminate\Support\Facades\DB;

class ThemeManager
{
    /**
     * The built-in default theme data, matching the Stitch "Premium Mobility
     * Design System" colors. MUST stay byte-identical to
     * resources/theme/clients/default.ts — the single source of truth for
     * the fallback value and for the seeded "Default" DB row. Having one
     * PHP constant prevents the fallback and the seed from drifting apart.
     *
     * @return array<string, mixed>
     */
    public static function defaultData(): array
    {
        return [
            'color' => [
                'primary'      => '#0A1F44',
                'primaryHover' => '#0D2857',
                'onPrimary'    => '#ffffff',
                'secondary'    => '#0047FF',
                'onSecondary'  => '#ffffff',
                'background'   => '#F8F9FF',
                'surface'      => '#ffffff',
                'surfaceRaised'=> '#ffffff',
                'text'         => '#0B1C30',
                'textMuted'    => '#64748B',
                'border'       => '#C5C6CF',
                'success'      => '#16A34A',
                'danger'       => '#BA1A1A',
                'warning'      => '#D97706',
                'focusRing'    => '#0047FF',
                'onPhoto'      => '#ffffff',
                'photoScrim'   => '#000000',
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
