<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Enums\Role;

/**
 * Add to any Filament Resource or Page to gate access by role.
 *
 * Override minimumRole() in the class to require a higher role:
 *
 *   protected static function minimumRole(): Role { return Role::Admin; }
 *
 * WHY a method and not a typed static property: PHP's trait composition rules
 * treat a class redefining a trait's typed static property with a different
 * initial value as a fatal error ("definition differs and is considered
 * incompatible"). A method override has no such restriction — the class method
 * simply shadows the trait method. Using a property here is always wrong.
 *
 * The default (Staff) is intentionally permissive within the panel —
 * any class that should require Admin must declare it explicitly.
 */
trait HasMinimumRole
{
    protected static function minimumRole(): Role
    {
        return Role::Staff;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAtLeast(static::minimumRole()) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    /** @param  mixed  $record */
    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    /** @param  mixed  $record */
    public static function canDelete($record): bool
    {
        return static::canAccess();
    }
}
