<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1) Seed vehicle_make_models (brand + model catalogue)
        |--------------------------------------------------------------------------
        */
        $makeModelRows = [
            // Toyota
            ['make' => 'Toyota', 'model' => 'Corolla'],
            ['make' => 'Toyota', 'model' => 'Camry'],
            ['make' => 'Toyota', 'model' => 'RAV4'],
            ['make' => 'Toyota', 'model' => 'Hilux'],
            ['make' => 'Toyota', 'model' => 'Yaris'],

            // Honda
            ['make' => 'Honda', 'model' => 'Civic'],
            ['make' => 'Honda', 'model' => 'Accord'],
            ['make' => 'Honda', 'model' => 'CR-V'],
            ['make' => 'Honda', 'model' => 'Fit'],
            ['make' => 'Honda', 'model' => 'HR-V'],

            // Nissan
            ['make' => 'Nissan', 'model' => 'Sentra'],
            ['make' => 'Nissan', 'model' => 'Altima'],
            ['make' => 'Nissan', 'model' => 'X-Trail'],
            ['make' => 'Nissan', 'model' => 'Navara'],
            ['make' => 'Nissan', 'model' => 'Micra'],

            // Ford
            ['make' => 'Ford', 'model' => 'Fiesta'],
            ['make' => 'Ford', 'model' => 'Focus'],
            ['make' => 'Ford', 'model' => 'Explorer'],
            ['make' => 'Ford', 'model' => 'Ranger'],
            ['make' => 'Ford', 'model' => 'Mustang'],

            // Hyundai
            ['make' => 'Hyundai', 'model' => 'i10'],
            ['make' => 'Hyundai', 'model' => 'i20'],
            ['make' => 'Hyundai', 'model' => 'Elantra'],
            ['make' => 'Hyundai', 'model' => 'Tucson'],
            ['make' => 'Hyundai', 'model' => 'Santa Fe'],

            // Kia
            ['make' => 'Kia', 'model' => 'Rio'],
            ['make' => 'Kia', 'model' => 'Cerato'],
            ['make' => 'Kia', 'model' => 'Sportage'],
            ['make' => 'Kia', 'model' => 'Sorento'],
            ['make' => 'Kia', 'model' => 'Picanto'],

            // Volkswagen
            ['make' => 'Volkswagen', 'model' => 'Polo'],
            ['make' => 'Volkswagen', 'model' => 'Golf'],
            ['make' => 'Volkswagen', 'model' => 'Passat'],
            ['make' => 'Volkswagen', 'model' => 'Tiguan'],
            ['make' => 'Volkswagen', 'model' => 'Jetta'],

            // Mercedes
            ['make' => 'Mercedes', 'model' => 'C-Class'],
            ['make' => 'Mercedes', 'model' => 'E-Class'],
            ['make' => 'Mercedes', 'model' => 'GLC'],
            ['make' => 'Mercedes', 'model' => 'GLE'],
            ['make' => 'Mercedes', 'model' => 'A-Class'],

            // BMW
            ['make' => 'BMW', 'model' => '3 Series'],
            ['make' => 'BMW', 'model' => '5 Series'],
            ['make' => 'BMW', 'model' => 'X3'],
            ['make' => 'BMW', 'model' => 'X5'],
            ['make' => 'BMW', 'model' => '1 Series'],

            // Tesla
            ['make' => 'Tesla', 'model' => 'Model 3'],
            ['make' => 'Tesla', 'model' => 'Model Y'],
            ['make' => 'Tesla', 'model' => 'Model S'],
            ['make' => 'Tesla', 'model' => 'Model X'],
        ];

        foreach ($makeModelRows as $row) {
            DB::table('vehicle_make_models')->updateOrInsert(
                [
                    'make'  => $row['make'],
                    'model' => $row['model'],
                ],
                [] // nothing to update for now
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Seed vehicle_types (with attributes.allowed_makes)
        |--------------------------------------------------------------------------
        */

        // All makes we have in the catalogue above
        $allMakes = [
            'Toyota',
            'Honda',
            'Nissan',
            'Ford',
            'Hyundai',
            'Kia',
            'Volkswagen',
            'Mercedes',
            'BMW',
            'Tesla',
        ];

        $types = [
            [
                'id'   => 1,
                'name' => 'Sedan',
                'attributes' => [
                    'allowed_makes' => $allMakes,
                ],
            ],
            [
                'id'   => 2,
                'name' => 'SUV / Crossover',
                'attributes' => [
                    'allowed_makes' => $allMakes,
                ],
            ],
            [
                'id'   => 3,
                'name' => 'Hatchback',
                'attributes' => [
                    'allowed_makes' => $allMakes,
                ],
            ],
            [
                'id'   => 4,
                'name' => 'Pickup / Truck',
                'attributes' => [
                    'allowed_makes' => ['Toyota', 'Nissan', 'Ford'],
                ],
            ],
            [
                'id'   => 5,
                'name' => 'Van / Minibus',
                'attributes' => [
                    'allowed_makes' => ['Toyota', 'Hyundai', 'Kia', 'Ford'],
                ],
            ],
            [
                'id'   => 6,
                'name' => 'Luxury',
                'attributes' => [
                    'allowed_makes' => ['Mercedes', 'BMW', 'Tesla'],
                ],
            ],
            [
                'id'   => 7,
                'name' => 'Electric',
                'attributes' => [
                    'allowed_makes' => ['Tesla'],
                ],
            ],
        ];

        foreach ($types as $type) {
            DB::table('vehicle_types')->updateOrInsert(
                ['id' => $type['id']], // match by id
                [
                    'name'       => $type['name'],
                    'slug'       => Str::slug($type['name']),
                    // JSON-encode for the DB column
                    'attributes' => isset($type['attributes'])
                        ? json_encode($type['attributes'])
                        : null,
                ]
            );
        }
    }
}
