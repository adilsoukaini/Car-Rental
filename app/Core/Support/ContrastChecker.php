<?php

declare(strict_types=1);

namespace App\Core\Support;

class ContrastChecker
{
    private const MIN_RATIO = 4.5;

    /**
     * WCAG 2.1 relative luminance of a hex color.
     * Formula: https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
     */
    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $linearize = static fn (float $c): float => $c <= 0.04045
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linearize($r)
             + 0.7152 * $linearize($g)
             + 0.0722 * $linearize($b);
    }

    /**
     * WCAG 2.1 contrast ratio between two hex colors.
     * Returns a value between 1.0 (no contrast) and 21.0 (black on white).
     */
    public static function ratio(string $hexA, string $hexB): float
    {
        $l1 = static::luminance($hexA);
        $l2 = static::luminance($hexB);

        [$lighter, $darker] = $l1 > $l2 ? [$l1, $l2] : [$l2, $l1];

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * Check the WCAG-critical pairs for a theme data array.
     * Returns a list of failure strings; empty array means all pairs pass.
     *
     * @param  array<string, mixed>  $data
     * @return string[]
     */
    public static function checkTheme(array $data): array
    {
        $pairs = [
            ['color.onPrimary',   'color.primary'],
            ['color.onSecondary', 'color.secondary'],
            ['color.text',        'color.background'],
            // Photo overlay pair — checked against solid colors; in practice photoScrim
            // is used at opacity over photos, so actual contrast is always higher than this.
            ['color.onPhoto',     'color.photoScrim'],
        ];

        $failures = [];

        foreach ($pairs as [$fg, $bg]) {
            $fgVal = (string) data_get($data, $fg, '');
            $bgVal = (string) data_get($data, $bg, '');

            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $fgVal) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $bgVal)) {
                $failures[] = "Cannot check contrast for {$fg}/{$bg}: one or both values are not valid hex colors.";

                continue;
            }

            $ratio = static::ratio($fgVal, $bgVal);

            if ($ratio < self::MIN_RATIO) {
                $failures[] = sprintf(
                    '%s (%s) on %s (%s) has a contrast ratio of %.2f:1, below the %.1f:1 WCAG AA minimum.',
                    $fg, $fgVal, $bg, $bgVal, $ratio, self::MIN_RATIO
                );
            }
        }

        return $failures;
    }
}
