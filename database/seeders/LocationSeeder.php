<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * Seeds the real Moroccan pickup/return locations this rental business
 * operates at — the major international airports plus city-center branches.
 *
 * Idempotent: keyed on Location.name (unique in practice), so re-running
 * `db:seed` updates addresses/coordinates rather than duplicating rows.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Casablanca Mohammed V Airport',
                'address_line' => 'Aéroport Mohammed V, Nouaceur',
                'city' => 'Casablanca',
                'country' => 'Morocco',
                'latitude' => 33.3675,
                'longitude' => -7.5898,
            ],
            [
                'name' => 'Marrakech Menara Airport',
                'address_line' => 'Aéroport Marrakech-Menara, Route de l\'Aéroport',
                'city' => 'Marrakech',
                'country' => 'Morocco',
                'latitude' => 31.6069,
                'longitude' => -8.0363,
            ],
            [
                'name' => 'Agadir Al Massira Airport',
                'address_line' => 'Aéroport Agadir-Al Massira, Route de l\'Aéroport',
                'city' => 'Agadir',
                'country' => 'Morocco',
                'latitude' => 30.3250,
                'longitude' => -9.4131,
            ],
            [
                'name' => 'Tangier Ibn Battouta Airport',
                'address_line' => 'Aéroport Tanger-Ibn Battouta, Route de l\'Aéroport',
                'city' => 'Tangier',
                'country' => 'Morocco',
                'latitude' => 35.7269,
                'longitude' => -5.9167,
            ],
            [
                'name' => 'Fes Saïss Airport',
                'address_line' => 'Aéroport Fès-Saïss, Route de l\'Aéroport',
                'city' => 'Fes',
                'country' => 'Morocco',
                'latitude' => 33.9273,
                'longitude' => -4.9780,
            ],
            [
                'name' => 'Rabat-Salé Airport',
                'address_line' => 'Aéroport Rabat-Salé, Route de l\'Aéroport',
                'city' => 'Salé',
                'country' => 'Morocco',
                'latitude' => 34.0508,
                'longitude' => -6.7515,
            ],
            [
                'name' => 'Casablanca City Center',
                'address_line' => 'Boulevard de la Corniche, Ain Diab',
                'city' => 'Casablanca',
                'country' => 'Morocco',
                'latitude' => 33.5950,
                'longitude' => -7.6186,
            ],
            [
                'name' => 'Marrakech City Center',
                'address_line' => 'Avenue Mohammed VI, Gueliz',
                'city' => 'Marrakech',
                'country' => 'Morocco',
                'latitude' => 31.6295,
                'longitude' => -7.9811,
            ],
            [
                'name' => 'Agadir City Center',
                'address_line' => 'Boulevard du 20 Août',
                'city' => 'Agadir',
                'country' => 'Morocco',
                'latitude' => 30.4278,
                'longitude' => -9.5981,
            ],
        ];

        foreach ($locations as $location) {
            Location::query()->updateOrCreate(
                ['name' => $location['name']],
                $location,
            );
        }
    }
}
