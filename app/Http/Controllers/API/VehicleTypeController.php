<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VehicleTypeController extends Controller
{
    public function index()
    {
        return response()->json(VehicleType::orderBy('name')->get());
    }

    public function show(VehicleType $vehicleType)
    {
        return response()->json($vehicleType);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:vehicle_types,name',
            'attributes' => 'nullable|array',
        ]);
        $data['slug'] = Str::slug($data['name']);
        $vt = VehicleType::create($data);
        return response()->json($vt, 201);
    }

    public function update(Request $request, VehicleType $vehicleType)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120|unique:vehicle_types,name,'.$vehicleType->id,
            'attributes' => 'nullable|array',
        ]);
        if (isset($data['name'])) $data['slug'] = Str::slug($data['name']);
        $vehicleType->update($data);
        return response()->json($vehicleType);
    }

    public function destroy(VehicleType $vehicleType)
    {
        $vehicleType->delete();
        return response()->json(['deleted' => true]);
    }
}
