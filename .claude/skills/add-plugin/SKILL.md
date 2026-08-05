---
name: add-plugin
description: Use this skill whenever the user asks to add a new feature or plugin to the car rental platform (e.g. "add insurance add-ons", "I want a maintenance-scheduling plugin"). Ensures the new feature is built as a self-contained Composer package following the project's Event/Pipeline/Service-Provider architecture. Do NOT use this for editing an existing plugin's internals, theme/token changes, or core kernel changes.
---

# Add a new plugin

## 1. Confirm scope first

- What core Events will this plugin listen to, and what Filters will it
  register into?
- What new data does it need — its own migrations, or just a `metadata`
  field on an existing core model?
- Does it depend on another plugin being enabled?

If a Filter/Slot it needs doesn't exist yet in `docs/event-registry.md`,
that's a signal core needs a new one added deliberately (document it when you
do), or the feature belongs inside an existing plugin instead.

## 2. Scaffold the package

```
/plugins/<plugin-slug>/
  composer.json
  src/
    <PluginName>ServiceProvider.php
    Listeners/
    Pipes/
    Models/
    Http/Controllers/         (only if it needs its own routes)
    Filament/Resources/        (only if it needs an admin screen)
  database/
    migrations/                 (only if it needs its own tables)
  resources/
    js/Components/               (React components registered into slots)
  config/
    <plugin-slug>.php             (plugin's own settings defaults)
```

## 3. Write the Service Provider — never write raw logic in core

```php
namespace Plugins\<PluginName>;

use Illuminate\Support\ServiceProvider;

class <PluginName>ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/<plugin-slug>.php', '<plugin-slug>');
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Event::listen(SomeCoreEvent::class, Listeners\SomeListener::class);
        FilterRegistry::register('some.filter', Pipes\SomePipe::class);
    }
}
```

Check: does anything in `src/` have a `use Plugins\OtherPlugin\...`? If yes,
stop — that dependency must go through an Event/Filter instead.

## 4. Register any UI into named slots — never edit a core Inertia page directly

Same pattern as core: `SlotRegistry::register('some.slot', '<PluginName>/SomeWidget')`
in `boot()`, add the component to the frontend slot component registry.

## 5. Add data — core migration vs plugin migration

Small addition to an existing entity → `metadata: json` on the core model.
New standalone concept → the plugin's own migration, loaded via
`loadMigrationsFrom()`.

Ask before running `php artisan migrate` against a real database.

## 6. Register in config

```php
// config/plugins.php
'registry' => [
    '<plugin-slug>' => \Plugins\<PluginName>\<PluginName>ServiceProvider::class,
],
```

Enable via the `plugins` DB table `is_enabled` flag, not by assuming it's on
by default.

## 7. Document any new Events/Filters/Slots

Add to `docs/event-registry.md`: name, when it fires/triggers, arguments,
which file dispatches it.

## 8. Verification checklist before calling it done

- [ ] Disabling this plugin leaves the rest of the app working with no
      broken references
- [ ] No file in this plugin has a `use Plugins\OtherPlugin\...`
- [ ] No component hardcodes a color/font/spacing value instead of using
      theme tokens
- [ ] New Events/Filters/Slots (if any) are documented
- [ ] If this plugin touches booking dates/availability, explicit test
      coverage exists for the overlap-checking logic
