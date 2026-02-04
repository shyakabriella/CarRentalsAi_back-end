<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    public function index()
    {
        return response()->json(MaintenanceRecord::with('vehicle')->latest()->paginate(20));
    }

    public function show(MaintenanceRecord $maintenanceRecord)
    {
        return response()->json($maintenanceRecord->load('vehicle'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id'=>'required|exists:vehicles,id',
            'type'=>'required|string',
            'title'=>'nullable|string',
            'notes'=>'nullable|string',
            'odometer_km'=>'nullable|integer',
            'cost'=>'nullable|numeric',
            'scheduled_at'=>'nullable|date',
            'completed_at'=>'nullable|date|after_or_equal:scheduled_at',
        ]);
        $rec = MaintenanceRecord::create($data);
        return response()->json($rec, 201);
    }

    public function update(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $data = $request->validate([
            'type'=>'nullable|string',
            'title'=>'nullable|string',
            'notes'=>'nullable|string',
            'odometer_km'=>'nullable|integer',
            'cost'=>'nullable|numeric',
            'scheduled_at'=>'nullable|date',
            'completed_at'=>'nullable|date|after_or_equal:scheduled_at',
        ]);
        $maintenanceRecord->update($data);
        return response()->json($maintenanceRecord);
    }

    public function destroy(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->delete();
        return response()->json(['deleted'=>true]);
    }
}
