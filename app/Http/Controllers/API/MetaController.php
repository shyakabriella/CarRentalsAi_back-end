<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MetaController extends Controller
{
    public function vehicleTypes()
    {
        $types = DB::table('vehicle_types')
            ->orderBy('id')
            ->get(['id', 'name']);

        return response()->json(['data' => $types]);
    }

    public function locations()
    {
        $locations = DB::table('locations')
            ->orderBy('id')
            ->get(['id', 'province', 'city']);

        return response()->json(['data' => $locations]);
    }

    /**
     * We return all make/model pairs; frontend will regroup into catalog.
     */
    public function vehicleMakeModels()
    {
        $rows = DB::table('vehicle_make_models')
            ->orderBy('make')
            ->orderBy('model')
            ->get(['make', 'model']);

        return response()->json(['data' => $rows]);
    }
}
