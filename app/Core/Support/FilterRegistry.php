<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Pipeline\Pipeline;

class FilterRegistry
{
    protected static array $pipes = [];

    /**
     * Register a pipe class into a named filter.
     *
     * Plugins call this from their ServiceProvider::boot().
     * Lower priority = runs first. Default 10 matches WordPress convention.
     */
    public static function register(string $filterName, string $pipeClass, int $priority = 10): void
    {
        static::$pipes[$filterName][] = ['class' => $pipeClass, 'priority' => $priority];
        usort(static::$pipes[$filterName], fn ($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Return the ordered list of pipe class names for a filter.
     * Used as the ->through() argument of a Pipeline.
     */
    public static function pipesFor(string $filterName): array
    {
        return array_column(static::$pipes[$filterName] ?? [], 'class');
    }

    /**
     * Run $value through all pipes registered to $filterName and return the result.
     *
     * Convenience wrapper so callers don't need to import Pipeline themselves.
     */
    public static function apply(string $filterName, mixed $value): mixed
    {
        $pipes = static::pipesFor($filterName);

        if (empty($pipes)) {
            return $value;
        }

        return app(Pipeline::class)
            ->send($value)
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Like apply(), but temporarily binds each entry in $bindings to the container
     * before running the pipeline so pipes can receive context via constructor injection.
     *
     * The bindings are removed in a finally block, even if a pipe throws.
     *
     * NOTE: these temporary bindings are NOT reentrant-safe. If a pipe triggered via
     * applyWithContext() itself calls applyWithContext() with the same abstract class,
     * the inner call's finally will forgetInstance() out from under the outer call.
     * No pipes in this codebase do this, but be explicit if adding nested usage.
     *
     * @param  array<class-string, object>  $bindings
     */
    public static function applyWithContext(string $filterName, mixed $value, array $bindings = []): mixed
    {
        foreach ($bindings as $abstract => $concrete) {
            app()->instance($abstract, $concrete);
        }

        try {
            return static::apply($filterName, $value);
        } finally {
            foreach ($bindings as $abstract => $_) {
                app()->forgetInstance($abstract);
            }
        }
    }

    /**
     * Clear all registered pipes.
     *
     * Static registry state survives across Application boots within the
     * same PHP process (every test method creates a fresh Application but
     * reuses the same process, and a persistent-worker deployment model
     * like Octane would too) — without this, every re-run of boot() would
     * silently accumulate duplicate pipe entries on top of whatever was
     * already registered. Called at the top of PluginManager::boot()
     * specifically so each boot cycle starts from a genuinely clean slate.
     */
    public static function flush(): void
    {
        static::$pipes = [];
    }
}
