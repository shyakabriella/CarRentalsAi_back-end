<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleMakeModelSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Seed vehicle_make_models (brand + model catalogue)
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
    }
}
