<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Services;

use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;

/**
 * Cast a stored string value to its typed representation for the frontend.
 *
 * A blank value casts to null so the display layer can skip it cleanly.
 * For 'select' types the stored key is resolved to its display label when
 * the definition's options are an associative map.
 */
class AttributeValueCaster
{
    public static function cast(VehicleAttributeDefinition $definition, ?string $raw): string|int|float|bool|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($definition->type) {
            'number' => is_numeric($raw) ? $raw + 0 : null,
            'boolean' => in_array(strtolower($raw), ['1', 'true', 'yes'], strict: true),
            'select' => self::resolveSelectLabel($definition, $raw),
            default => $raw, // text, textarea
        };
    }

    private static function resolveSelectLabel(VehicleAttributeDefinition $definition, string $raw): string
    {
        $options = $definition->options ?? [];

        // Associative map: {"full": "Full Coverage", "basic": "Basic"}
        if (array_is_list($options) === false && isset($options[$raw])) {
            return (string) $options[$raw];
        }

        return $raw;
    }
}
