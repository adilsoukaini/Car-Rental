<?php

namespace Database\Seeders;

use App\Core\Support\PluginManager;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with realistic demo data.
     *
     * Order matters: locations first (vehicles reference them), vehicles
     * next, images last (they reference vehicles). ThemeSeeder and the demo
     * users are independent. All registered plugins are activated up front
     * so the storefront and admin panel are fully usable out of the box.
     */
    public function run(): void
    {
        $this->activatePluginsAndRunPluginMigrations();

        $this->call([
            LocationSeeder::class,
            VehicleSeeder::class,
            VehicleImageSeeder::class,
            ThemeSeeder::class,
        ]);

        // Demo users — one per role, so both the storefront (customer) and
        // the admin panel (staff/admin) can be exercised. Password is
        // 'password' for all three.
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => Role::Admin,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'email' => 'staff@example.com',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => Role::Staff,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Demo Customer',
                'email' => 'user@example.com',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => Role::Customer,
            ],
        );
    }

    /**
     * Activate every registered plugin and make sure its migrations have
     * actually run — both are needed on a truly fresh database.
     *
     * Plugin-owned tables (e.g. vehicle-media's `vehicle_images`) live in the
     * plugin's own migrations, which are only loaded when that plugin's
     * ServiceProvider boots — and a provider only boots when the plugin is
     * enabled in the `plugins` table. During `migrate:fresh` a brand-new DB
     * has an empty `plugins` table, so those migrations never run. Enabling
     * the plugins here, then booting their providers so their migration paths
     * register with the migrator, then running `migrate` (a no-op for every
     * already-run core migration) closes that gap — so a single
     * `migrate:fresh --seed` produces a fully-populated demo, images included.
     */
    private function activatePluginsAndRunPluginMigrations(): void
    {
        foreach (array_keys(config('plugins.registry', [])) as $slug) {
            PluginManager::activate($slug);

            $providerClass = config("plugins.registry.{$slug}");
            if (is_string($providerClass) && class_exists($providerClass)) {
                app()->register($providerClass);
            }
        }

        // Runs any migrations not yet recorded in the migrations table — the
        // plugin-owned ones just registered above on a fresh install. Core
        // migrations are all already-run, so this is a no-op for them.
        Artisan::call('migrate', ['--force' => true]);
    }
}
