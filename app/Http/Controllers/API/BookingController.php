<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Vehicle, Driver, Customer};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $q = Booking::with(['customer.user','driver.user','vehicle','pickupLocation','dropoffLocation'])
            ->orderBy('created_at','desc');

        if ($request->filled('status')) $q->where('status',$request->status);
        if ($request->filled('customer_id')) $q->where('customer_id',$request->customer_id);
        if ($request->filled('driver_id')) $q->where('driver_id',$request->driver_id);

        return response()->json($q->paginate(20));
    }

    public function show(Booking $booking)
    {
        return response()->json($booking->load(['customer.user','driver.user','vehicle','pickupLocation','dropoffLocation','payments']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'=>'required|exists:customers,id',
            'vehicle_id'=>'nullable|exists:vehicles,id',
            'driver_id'=>'nullable|exists:drivers,id',
            'pickup_time'=>'required|date',
            'dropoff_time'=>'nullable|date|after:pickup_time',
            'pickup_location_id'=>'nullable|exists:locations,id',
            'dropoff_location_id'=>'nullable|exists:locations,id',
            'currency'=>'nullable|string|size:3',
            'price_subtotal'=>'nullable|numeric',
            'price_driver_fee'=>'nullable|numeric',
            'price_taxes'=>'nullable|numeric',
            'price_total'=>'nullable|numeric',
            'meta'=>'nullable|array',
        ]);

        $data['code'] = 'SC-'.now()->format('Y').'-'.str_pad((string) (Booking::max('id')+1), 6, '0', STR_PAD_LEFT);
        $data['status'] = 'pending';
        $data['payment_status'] = 'unpaid';
        $data['currency'] = $data['currency'] ?? 'RWF';

        // Simple price auto-calc if not provided
        if (!isset($data['price_total'])) {
            $subtotal = $data['price_subtotal'] ?? 0;
            $driverFee = $data['price_driver_fee'] ?? 0;
            $taxes = $data['price_taxes'] ?? 0;
            $data['price_total'] = $subtotal + $driverFee + $taxes;
        }

        $booking = Booking::create($data);
        return response()->json($booking, 201);
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'vehicle_id'=>'nullable|exists:vehicles,id',
            'driver_id'=>'nullable|exists:drivers,id',
            'pickup_time'=>'nullable|date',
            'dropoff_time'=>'nullable|date|after:pickup_time',
            'pickup_location_id'=>'nullable|exists:locations,id',
            'dropoff_location_id'=>'nullable|exists:locations,id',
            'currency'=>'nullable|string|size:3',
            'price_subtotal'=>'nullable|numeric',
            'price_driver_fee'=>'nullable|numeric',
            'price_taxes'=>'nullable|numeric',
            'price_total'=>'nullable|numeric',
            'meta'=>'nullable|array',
        ]);
        $booking->update($data);
        return response()->json($booking);
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();
        return response()->json(['deleted'=>true]);
    }

    public function assignDriver(Request $request, Booking $booking)
    {
        $data = $request->validate(['driver_id'=>'required|exists:drivers,id']);
        $booking->update(['driver_id'=>$data['driver_id']]);
        return response()->json($booking->load('driver.user'));
    }

    public function setStatus(Request $request, Booking $booking)
    {
        $data = $request->validate(['status'=>'required|string']); // pending|confirmed|in_progress|completed|cancelled
        $booking->update(['status'=>$data['status']]);
        return response()->json($booking);
    }
}
