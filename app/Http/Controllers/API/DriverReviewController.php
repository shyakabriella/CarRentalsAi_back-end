<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{DriverReview, Driver, Booking};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverReviewController extends Controller
{
    public function index()
    {
        return response()->json(DriverReview::with(['driver.user','customer.user','booking'])->latest()->paginate(20));
    }

    public function show(DriverReview $driverReview)
    {
        return response()->json($driverReview->load(['driver.user','customer.user','booking']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id'=>'required|exists:bookings,id',
            'driver_id'=>'required|exists:drivers,id',
            'customer_id'=>'required|exists:customers,id',
            'rating'=>'required|integer|min:1|max:5',
            'review'=>'nullable|string',
        ]);

        // Ensure one review per booking per customer
        if (DriverReview::where('booking_id',$data['booking_id'])->where('customer_id',$data['customer_id'])->exists()) {
            return response()->json(['message'=>'Already reviewed'], 422);
        }

        $review = DriverReview::create($data);

        // Update aggregates
        $driver = Driver::findOrFail($data['driver_id']);
        $count = DriverReview::where('driver_id',$driver->id)->count();
        $avg = DriverReview::where('driver_id',$driver->id)->avg('rating');
        $driver->update(['rating_avg'=>$avg ?? 0, 'rating_count'=>$count]);

        return response()->json($review, 201);
    }

    public function update(Request $request, DriverReview $driverReview)
    {
        $data = $request->validate([
            'rating'=>'sometimes|required|integer|min:1|max:5',
            'review'=>'nullable|string',
        ]);
        $driverReview->update($data);

        $driver = $driverReview->driver;
        $count = DriverReview::where('driver_id',$driver->id)->count();
        $avg = DriverReview::where('driver_id',$driver->id)->avg('rating');
        $driver->update(['rating_avg'=>$avg ?? 0, 'rating_count'=>$count]);

        return response()->json($driverReview);
    }

    public function destroy(DriverReview $driverReview)
    {
        $driver = $driverReview->driver;
        $driverReview->delete();

        $count = DriverReview::where('driver_id',$driver->id)->count();
        $avg = DriverReview::where('driver_id',$driver->id)->avg('rating');
        $driver->update(['rating_avg'=>$avg ?? 0, 'rating_count'=>$count]);

        return response()->json(['deleted'=>true]);
    }
}
