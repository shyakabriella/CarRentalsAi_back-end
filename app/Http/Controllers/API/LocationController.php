<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index() { return response()->json(Location::orderBy('name')->get()); }
    public function show(Location $location) { return response()->json($location); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:150',
            'address'=>'nullable|string|max:255',
            'lat'=>'nullable|numeric',
            'lng'=>'nullable|numeric',
        ]);
        return response()->json(Location::create($data), 201);
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name'=>'sometimes|required|string|max:150',
            'address'=>'nullable|string|max:255',
            'lat'=>'nullable|numeric',
            'lng'=>'nullable|numeric',
        ]);
        $location->update($data);
        return response()->json($location);
    }

    public function destroy(Location $location)
    {
        $location->delete();
        return response()->json(['deleted'=>true]);
    }
}
