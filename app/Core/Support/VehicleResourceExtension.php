<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Lets plugins extend VehicleResource without modifying the core resource
 * class directly (Hard Rule 1) — same pattern as
 * PaymentGatewayRegistry/SlotRegistry. VehicleResource::getRelations()
 * delegates here, and VehicleForm::configure() merges any registered form
 * sections; core never imports the plugin's relation manager class or form
 * section class.
 *
 * A form section is a callable that receives the resource's form Schema and
 * returns an array of Filament components to append to the create/edit form.
 * The callable is registered by the plugin (a closure in its
 * ServiceProvider), so core only ever invokes it — it never references the
 * plugin's namespace.
 */
class VehicleResourceExtension
{
    /** @var list<class-string> */
    protected static array $relationManagers = [];

    /** @var list<callable> */
    protected static array $formSections = [];

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

    public static function addFormSection(callable $callback): void
    {
        static::$formSections[] = $callback;
    }

    /** @return list<callable> */
    public static function getFormSections(): array
    {
        return static::$formSections;
    }

    /** For testing. */
    public static function flush(): void
    {
        static::$relationManagers = [];
        static::$formSections = [];
    }
}
