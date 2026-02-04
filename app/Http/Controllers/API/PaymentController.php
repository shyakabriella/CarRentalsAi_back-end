<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Payment, Booking};
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        return response()->json(Payment::with('booking')->latest()->paginate(20));
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load('booking'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id'=>'required|exists:bookings,id',
            'gateway'=>'required|string',
            'reference'=>'required|string',
            'status'=>'nullable|string',
            'currency'=>'nullable|string|size:3',
            'amount'=>'required|numeric|min:0',
            'paid_at'=>'nullable|date',
            'meta'=>'nullable|array',
        ]);
        $data['status'] = $data['status'] ?? 'pending';
        $data['currency'] = $data['currency'] ?? 'RWF';

        $payment = Payment::create($data);
        return response()->json($payment, 201);
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'status'=>'nullable|string',
            'paid_at'=>'nullable|date',
            'meta'=>'nullable|array',
        ]);
        $payment->update($data);
        return response()->json($payment);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->json(['deleted'=>true]);
    }

    public function markPaid(Request $request, Payment $payment)
    {
        $payment->update([
            'status'=>'succeeded',
            'paid_at'=>now(),
        ]);

        // Update booking payment_status naively (you can sum amounts)
        $booking = $payment->booking;
        if ($booking) $booking->update(['payment_status'=>'paid']);

        return response()->json($payment->load('booking'));
    }
}
