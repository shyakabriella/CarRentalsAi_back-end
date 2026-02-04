<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DriverAvailability;
use Illuminate\Http\Request;

class DriverAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $q = DriverAvailability::with('driver.user')->orderBy('starts_at','desc');
        if ($request->filled('driver_id')) $q->where('driver_id',$request->driver_id);
        return response()->json($q->paginate(20));
    }

    public function show(DriverAvailability $driverAvailability)
    {
        return response()->json($driverAvailability->load('driver.user'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_id'=>'required|exists:drivers,id',
            'starts_at'=>'required|date',
            'ends_at'=>'required|date|after:starts_at',
            'status'=>'nullable|string',
            'meta'=>'nullable|array',
        ]);
        $data['status'] = $data['status'] ?? 'available';
        return response()->json(DriverAvailability::create($data), 201);
    }

    public function update(Request $request, DriverAvailability $driverAvailability)
    {
        $data = $request->validate([
            'starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date|after:starts_at',
            'status'=>'nullable|string',
            'meta'=>'nullable|array',
        ]);
        $driverAvailability->update($data);
        return response()->json($driverAvailability);
    }

    public function destroy(DriverAvailability $driverAvailability)
    {
        $driverAvailability->delete();
        return response()->json(['deleted'=>true]);
    }
}
