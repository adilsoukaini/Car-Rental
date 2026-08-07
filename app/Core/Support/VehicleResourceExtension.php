<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Lets plugins add Filament relation managers to VehicleResource without
 * modifying the core resource class directly (Hard Rule 1) — same pattern
 * as PaymentGatewayRegistry/SlotRegistry. VehicleResource::getRelations()
 * delegates here; core never imports the plugin's relation manager class.
 */
class VehicleResourceExtension
{
    /** @var list<class-string> */
    protected static array $relationManagers = [];

    /** @param  class-string  $class */
    public static function addRelationManager(string $class): void
    {
        static::$relationManagers[] = $class;
    }

    /** @return list<class-string> */
    public static function getRelationManagers(): array
    {
        return static::$relationManagers;
    }

    /** For testing. */
    public static function flush(): void
    {
        static::$relationManagers = [];
    }
}
