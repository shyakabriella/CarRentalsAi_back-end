<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // Kigali City (1–3)
            ['id' => 1,  'province' => 'Kigali City',       'city' => 'Nyarugenge'],
            ['id' => 2,  'province' => 'Kigali City',       'city' => 'Gasabo'],
            ['id' => 3,  'province' => 'Kigali City',       'city' => 'Kicukiro'],

            // Northern Province (4–8)
            ['id' => 4,  'province' => 'Northern Province', 'city' => 'Burera'],
            ['id' => 5,  'province' => 'Northern Province', 'city' => 'Gakenke'],
            ['id' => 6,  'province' => 'Northern Province', 'city' => 'Gicumbi'],
            ['id' => 7,  'province' => 'Northern Province', 'city' => 'Musanze'],
            ['id' => 8,  'province' => 'Northern Province', 'city' => 'Rulindo'],

            // Southern Province (9–16)
            ['id' => 9,  'province' => 'Southern Province', 'city' => 'Gisagara'],
            ['id' => 10, 'province' => 'Southern Province', 'city' => 'Huye'],
            ['id' => 11, 'province' => 'Southern Province', 'city' => 'Kamonyi'],
            ['id' => 12, 'province' => 'Southern Province', 'city' => 'Muhanga'],
            ['id' => 13, 'province' => 'Southern Province', 'city' => 'Nyamagabe'],
            ['id' => 14, 'province' => 'Southern Province', 'city' => 'Nyanza'],
            ['id' => 15, 'province' => 'Southern Province', 'city' => 'Nyaruguru'],
            ['id' => 16, 'province' => 'Southern Province', 'city' => 'Ruhango'],

            // Eastern Province (17–23)
            ['id' => 17, 'province' => 'Eastern Province',  'city' => 'Bugesera'],
            ['id' => 18, 'province' => 'Eastern Province',  'city' => 'Gatsibo'],
            ['id' => 19, 'province' => 'Eastern Province',  'city' => 'Kayonza'],
            ['id' => 20, 'province' => 'Eastern Province',  'city' => 'Kirehe'],
            ['id' => 21, 'province' => 'Eastern Province',  'city' => 'Ngoma'],
            ['id' => 22, 'province' => 'Eastern Province',  'city' => 'Nyagatare'],
            ['id' => 23, 'province' => 'Eastern Province',  'city' => 'Rwamagana'],

            // Western Province (24–30)
            ['id' => 24, 'province' => 'Western Province',  'city' => 'Karongi'],
            ['id' => 25, 'province' => 'Western Province',  'city' => 'Ngororero'],
            ['id' => 26, 'province' => 'Western Province',  'city' => 'Nyabihu'],
            ['id' => 27, 'province' => 'Western Province',  'city' => 'Nyamasheke'],
            ['id' => 28, 'province' => 'Western Province',  'city' => 'Rubavu'],
            ['id' => 29, 'province' => 'Western Province',  'city' => 'Rusizi'],
            ['id' => 30, 'province' => 'Western Province',  'city' => 'Rutsiro'],
        ];

        foreach ($locations as $loc) {
            // Example name format: "Nyarugenge - Kigali City"
            $name = $loc['city'].' - '.$loc['province'];

            DB::table('locations')->updateOrInsert(
                ['id' => $loc['id']],
                ['name' => $name]
            );
        }
    }
}
