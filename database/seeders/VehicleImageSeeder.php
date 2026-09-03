<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a real primary photo for each known vehicle from Wikimedia Commons
 * thumbnail URLs (e.g. https://thumb.wikimedia.org/.../960px-*.jpg).
 *
 * Design decisions:
 * - The `path` column stores the full remote URL. The VehicleImage model's
 *   `url()` accessor (and GetVehicleGalleryPipe) resolve any http(s) path
 *   as-is, so the seeded URLs render directly in the browser without any
 *   local file storage.
 * - The map is keyed by lowercase "make model" so it is stable across
 *   environments (vehicle ids are not portable). Vehicles that are not in the
 *   map (e.g. newly-added models with no curated photo yet) are skipped — the
 *   UI falls back to the VehiclePlaceholderIcon for those, so no broken rows
 *   are created.
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
    /**
     * Wikimedia Commons 960px thumbnail URLs, keyed by lowercase "make model".
     * Each URL was verified to return HTTP 200 (image/jpeg) when chosen.
     *
     * @var array<string, string>
     */
    private const REAL_IMAGES = [
        'dacia sandero' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/1/1d/2024_Dacia_Sandero_III_GIMS_2024_1X7A2023.jpg/960px-2024_Dacia_Sandero_III_GIMS_2024_1X7A2023.jpg',
        'dacia logan' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d5/2023_Dacia_Logan_III_IMG_9678.jpg/960px-2023_Dacia_Logan_III_IMG_9678.jpg',
        'dacia duster' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/43/Dacia_Duster_III_GIMS_2024_1X7A2248.jpg/960px-Dacia_Duster_III_GIMS_2024_1X7A2248.jpg',
        'renault clio 5' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b5/2024_Renault_Clio_Evolution_E-Tech_HEV_Auto.jpg/960px-2024_Renault_Clio_Evolution_E-Tech_HEV_Auto.jpg',
        'renault captur' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/25/2024_Renault_Captur_II_IMG_9974.jpg/960px-2024_Renault_Captur_II_IMG_9974.jpg',
        'peugeot 208' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/cb/2024_Peugeot_208_GT_PureTech_-_1200cc_1.2_%28100PS%29_Petrol_-_Agueda_Yellow_-_06-2024%2C_Front.jpg/960px-2024_Peugeot_208_GT_PureTech_-_1200cc_1.2_%28100PS%29_Petrol_-_Agueda_Yellow_-_06-2024%2C_Front.jpg',
        'peugeot 308' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/bd/2023_Peugeot_308_1.2_PureTech_GT_%28Front_1%29.jpg/960px-2023_Peugeot_308_1.2_PureTech_GT_%28Front_1%29.jpg',
        'hyundai i10' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0d/2023_Hyundai_i10_%28AC3%29_Autofr%C3%BChling_Ulm_IMG_9349.jpg/960px-2023_Hyundai_i10_%28AC3%29_Autofr%C3%BChling_Ulm_IMG_9349.jpg',
        'hyundai tucson' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/cf/Hyundai_Tucson_%28NX4%2C_SWB%29_Facelift_Sindelfingen_2024_IMG_9232.jpg/960px-Hyundai_Tucson_%28NX4%2C_SWB%29_Facelift_Sindelfingen_2024_IMG_9232.jpg',
        'toyota yaris' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/07/2024_Toyota_Yaris_Hybrid_130_%28XP210%29_IMG_9425.jpg/960px-2024_Toyota_Yaris_Hybrid_130_%28XP210%29_IMG_9425.jpg',
        'toyota corolla' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c1/2024_Toyota_Corolla_GR_Sport_HEV_-_1987cc_2.0_%28196PS%29_Petrol_Hybrid_-_Super_Green_-_07-2024%2C_Front.jpg/960px-2024_Toyota_Corolla_GR_Sport_HEV_-_1987cc_2.0_%28196PS%29_Petrol_Hybrid_-_Super_Green_-_07-2024%2C_Front.jpg',
        'toyota rav4' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d0/2024_Toyota_RAV_4_GR_Sport_PHEV_Auto.jpg/960px-2024_Toyota_RAV_4_GR_Sport_PHEV_Auto.jpg',
        'mercedes c-class' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/49/2024_Mercedes-Benz_W206_C180_Avantgarde_in_Mojave_Silver%2C_front_right%2C_07-10-2024.jpg/960px-2024_Mercedes-Benz_W206_C180_Avantgarde_in_Mojave_Silver%2C_front_right%2C_07-10-2024.jpg',
        'bmw 3 series' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/7/76/BMW_3-Series_%28G20%29_330i_xDrive_%282024%29_%2854016330027%29.jpg/960px-BMW_3-Series_%28G20%29_330i_xDrive_%282024%29_%2854016330027%29.jpg',
        'audi q5' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/6e/2021_Audi_Q5_S_Line_45_TFSi_MHEV_Quattro_facelift_2.0_Front.jpg/960px-2021_Audi_Q5_S_Line_45_TFSi_MHEV_Quattro_facelift_2.0_Front.jpg',
        'range rover evoque' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a8/Range_Rover_Evoque_%28L551%29_1X7A1765.jpg/960px-Range_Rover_Evoque_%28L551%29_1X7A1765.jpg',
        'jeep wrangler' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a7/JEEP_WRANGLER_%28JL%29_China.jpg/960px-JEEP_WRANGLER_%28JL%29_China.jpg',
        'renault trafic' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/41/2024_Renault_Trafic_Start_Blue_dCi_-_1997cc_2.0_%28130PS%29_Diesel_-_Silver_-_05-2024%2C_Front.jpg/960px-2024_Renault_Trafic_Start_Blue_dCi_-_1997cc_2.0_%28130PS%29_Diesel_-_Silver_-_05-2024%2C_Front.jpg',
        'mercedes v-class' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/5/51/2024_Mercedes_V_Class.jpg/960px-2024_Mercedes_V_Class.jpg',
        'vw transporter' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d7/2024_Volkswagen_Transporter_T32_TDi_Highline.jpg/960px-2024_Volkswagen_Transporter_T32_TDi_Highline.jpg',
        'mini cooper' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/ac/2024_Mini_Cooper_S_2.jpg/960px-2024_Mini_Cooper_S_2.jpg',
        'bmw z4' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d2/BMW_Z4_M40i_%28G29%2C_2024%29_%2854733528698%29.jpg/960px-BMW_Z4_M40i_%28G29%2C_2024%29_%2854733528698%29.jpg',
    ];

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

        $seeded = 0;

        foreach ($vehicles as $vehicle) {
            $key = strtolower(trim("{$vehicle->make} {$vehicle->model}"));

            if (! isset(self::REAL_IMAGES[$key])) {
                continue; // No curated real photo for this model — leave the card to its placeholder icon.
            }

            DB::table('vehicle_images')->insert([
                'vehicle_id' => $vehicle->id,
                'path' => self::REAL_IMAGES[$key],
                'alt_text' => "{$vehicle->make} {$vehicle->model} — photo 1",
                'sort_order' => 0,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $seeded++;
        }

        $this->command?->info(
            '[VehicleImageSeeder] seeded '.$seeded.' vehicles with a real primary photo.',
        );
    }
}
