<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\DTOs\ThemeValidationResult;

/**
 * Extensible so a future plugin could register its own token field
 * (e.g. a vehicle-card-specific token) without this class needing to
 * change — see docs/event-registry.md's "Theme System" section.
 */
class ThemeSchemaRegistry
{
    /** @var array<string, array{type: string, required: bool}> dot-path => spec */
    protected static array $fields = [];

    public static function registerField(string $path, string $type, bool $required = true): void
    {
        static::$fields[$path] = compact('type', 'required');
    }

    /** @return array<string, array{type: string, required: bool}> */
    public static function fields(): array
    {
        return static::$fields;
    }

    /** @param array<string, mixed> $data */
    public static function validate(array $data): ThemeValidationResult
    {
        $errors = [];

        foreach (static::$fields as $path => $spec) {
            $value = data_get($data, $path);

            if ($spec['required'] && $value === null) {
                $errors[] = "Missing required field: {$path}";

                continue;
            }

            if ($value !== null && $spec['type'] === 'color' && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                $errors[] = "Field {$path} must be a 6-digit hex color (e.g. #2563eb), got: {$value}";
            }
        }

        return new ThemeValidationResult(empty($errors), $errors);
    }

    /** Reset all registered fields — used in tests. */
    public static function reset(): void
    {
        static::$fields = [];
    }
}
