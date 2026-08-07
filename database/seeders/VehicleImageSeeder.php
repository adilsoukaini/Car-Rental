<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds gallery images for every vehicle from picsum.photos placeholder
 * URLs (e.g. https://picsum.photos/seed/renault-clio-2022/800/600).
 *
 * Design decisions:
 * - The `path` column stores the full remote URL. The VehicleImage model's
 *   `url()` accessor (and GetVehicleGalleryPipe) resolve any http(s) path
 *   as-is, so the seeded URLs render directly in the browser without any
 *   local file storage — ideal for demo/dev where network at seed time is
 *   not guaranteed (the browser fetches the image later, if/when online).
 * - Inserted via DB::table() rather than the VehicleImage model: the model
 *   lives in the vehicle-media plugin, and a core-owned seeder must not
 *   reference plugin classes (Hard Rule 1). The insert is guarded by
 *   Schema::hasTable() so this seeder is a no-op if the plugin is disabled
 *   or its migration hasn't run (e.g. a truly fresh migrate:fresh --seed,
 *   where no plugin is enabled at boot time so plugin migrations don't run).
 *
 * Idempotent: all existing vehicle_images rows are replaced on re-run.
 */
class VehicleImageSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('vehicle_images')) {
            $this->command?->warn(
                '[VehicleImageSeeder] skipping: `vehicle_images` table does not exist. '
                .'Enable the vehicle-media plugin and run its migration to seed images.',
            );

            return;
        }

        DB::table('vehicle_images')->delete();

        $vehicles = Vehicle::query()->orderBy('id')->get();

        foreach ($vehicles as $index => $vehicle) {
            $count = 1 + ($index % 3); // 1-3 images per vehicle

            for ($i = 0; $i < $count; $i++) {
                $slug = Str::slug("{$vehicle->make} {$vehicle->model} {$vehicle->year}");

                DB::table('vehicle_images')->insert([
                    'vehicle_id' => $vehicle->id,
                    'path' => "https://picsum.photos/seed/{$slug}-{$i}/800/600",
                    'alt_text' => "{$vehicle->make} {$vehicle->model} — photo ".($i + 1),
                    'sort_order' => $i,
                    'is_primary' => $i === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command?->info(
            '[VehicleImageSeeder] seeded '.count($vehicles).' vehicles with gallery images.',
        );
    }
}
