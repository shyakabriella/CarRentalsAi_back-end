<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiMatchLog;
use Illuminate\Http\Request;

class AiMatchLogController extends Controller
{
    public function index(Request $request)
    {
        $q = AiMatchLog::with(['booking','driver.user'])->orderBy('decided_at','desc');
        if ($request->filled('booking_id')) $q->where('booking_id',$request->booking_id);
        return response()->json($q->paginate(20));
    }

    public function show(AiMatchLog $aiMatchLog)
    {
        return response()->json($aiMatchLog->load(['booking','driver.user']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id'=>'required|exists:bookings,id',
            'driver_id'=>'nullable|exists:drivers,id',
            'score'=>'nullable|numeric',
            'features'=>'nullable|array',
            'decided_at'=>'nullable|date',
        ]);
        $data['decided_at'] = $data['decided_at'] ?? now();
        return response()->json(AiMatchLog::create($data), 201);
    }

    public function update(Request $request, AiMatchLog $aiMatchLog)
    {
        $data = $request->validate([
            'driver_id'=>'nullable|exists:drivers,id',
            'score'=>'nullable|numeric',
            'features'=>'nullable|array',
            'decided_at'=>'nullable|date',
        ]);
        $aiMatchLog->update($data);
        return response()->json($aiMatchLog);
    }

    public function destroy(AiMatchLog $aiMatchLog)
    {
        $aiMatchLog->delete();
        return response()->json(['deleted'=>true]);
    }
}
