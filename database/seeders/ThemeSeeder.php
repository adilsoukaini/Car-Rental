<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Support\ThemeManager;
use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * Seeds this project's two EXISTING file-based themes
 * (resources/theme/clients/default.ts and client-swap-proof-DISPOSABLE.ts)
 * as the first rows in the new admin-driven `themes` table, so activating
 * the centralized system doesn't change what's currently rendered — the
 * "Default" row's data is byte-identical to ThemeManager::defaultData()
 * (which itself mirrors default.ts), and is the one marked active.
 *
 * "Demo Rentals" mirrors client-swap-proof-DISPOSABLE.ts exactly as it
 * exists today — its own file explicitly says it's a disposable swap-proof,
 * not a real client, so its font tokens are NOT updated to the new
 * Space Grotesk/JetBrains Mono choice; only the Default theme's font
 * tokens change, per the frontend-foundation-phase doc.
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::query()->firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'data' => ThemeManager::defaultData(),
                'is_active' => true,
            ],
        );

        Theme::query()->firstOrCreate(
            ['slug' => 'demo-rentals'],
            [
                'name' => 'Demo Rentals (swap-proof, not a real client)',
                'data' => [
                    'color' => [
                        'primary' => '#0f5132',
                        'primaryHover' => '#146c43',
                        'onPrimary' => '#ffffff',
                        'secondary' => '#f0a500',
                        'onSecondary' => '#1a1a1a',
                        'background' => '#f5f7f5',
                        'surface' => '#ffffff',
                        'surfaceRaised' => '#ffffff',
                        'text' => '#1a1a1a',
                        'textMuted' => '#52525b',
                        'border' => '#d4d4d8',
                        'success' => '#16a34a',
                        'danger' => '#dc2626',
                        'warning' => '#d97706',
                        'focusRing' => '#0f5132',
                        'onPhoto' => '#ffffff',
                        'photoScrim' => '#000000',
                    ],
                    'font' => [
                        'display' => '"Poppins", sans-serif',
                        'body' => '"Inter", sans-serif',
                        'mono' => '"JetBrains Mono", monospace',
                    ],
                    'radius' => [
                        'interactive' => '16px',
                        'container' => '16px',
                        'pill' => '9999px',
                    ],
                    'shadow' => [
                        'resting' => '0 1px 2px rgba(0,0,0,0.05)',
                        'raised' => '0 4px 6px rgba(0,0,0,0.1)',
                        'overlay' => '0 10px 15px rgba(0,0,0,0.1)',
                    ],
                ],
                'is_active' => false,
            ],
        );
    }
}
