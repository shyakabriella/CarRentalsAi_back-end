<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Vehicle, Driver, Customer};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $q = Booking::with(['customer.user','driver.user','vehicle','pickupLocation','dropoffLocation'])
            ->orderBy('created_at','desc');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('customer_id')) $q->where('customer_id', $request->customer_id);
        if ($request->filled('driver_id')) $q->where('driver_id', $request->driver_id);

        return response()->json($q->paginate(20));
    }

    public function show(Booking $booking)
    {
        return response()->json(
            $booking->load(['customer.user','driver.user','vehicle','pickupLocation','dropoffLocation','payments'])
        );
    }

    /**
     * ✅ Car booking create (customer flow)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'          => ['nullable','exists:customers,id'],
            'vehicle_id'           => ['required','exists:vehicles,id'],
            'driver_id'            => ['nullable','exists:drivers,id'],

            'pickup_time'          => ['required','date'],
            'dropoff_time'         => ['nullable','date','after:pickup_time'],

            'pickup_location_id'   => ['nullable','exists:locations,id'],
            'dropoff_location_id'  => ['nullable','exists:locations,id'],

            'pickup_address'       => ['nullable','string','max:255'],
            'dropoff_address'      => ['nullable','string','max:255'],

            'currency'             => ['nullable','string','size:3'],

            'price_subtotal'       => ['nullable','numeric','min:0'],
            'price_driver_fee'     => ['nullable','numeric','min:0'],
            'price_taxes'          => ['nullable','numeric','min:0'],
            'price_total'          => ['nullable','numeric','min:0'],

            'meta'                 => ['nullable','array'],
        ]);

        // ✅ Resolve customer_id from auth user if not provided
        $data['customer_id'] = $this->resolveCustomerId($request, $data['customer_id'] ?? null);

        // ✅ Load vehicle
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        // ✅ Optional: check vehicle status (if column exists)
        if (Schema::hasColumn('vehicles', 'status')) {
            $status = strtolower((string)($vehicle->status ?? ''));
            if ($status && !in_array($status, ['available', 'in_service'], true)) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ["This vehicle is not available right now (status: {$status})."],
                ]);
            }
        }

        // ✅ Optional: basic driver check
        if (!empty($data['driver_id'])) {
            Driver::findOrFail($data['driver_id']);
        }

        // ✅ Merge meta fields (include addresses)
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if (!empty($data['pickup_address'])) $meta['pickup_address'] = $data['pickup_address'];
        if (!empty($data['dropoff_address'])) $meta['dropoff_address'] = $data['dropoff_address'];

        unset($data['pickup_address'], $data['dropoff_address']);

        // ✅ Determine rental days
        $days = $this->calcRentalDays($data['pickup_time'], $data['dropoff_time'] ?? null);
        $meta['renter_days'] = $meta['renter_days'] ?? $days;

        // ✅ Auto-calc prices if price_total not provided
        $data['currency'] = $data['currency'] ?? 'RWF';
        $taxRate = (float)($meta['tax_rate'] ?? 0.08);
        $driverFeePerDay = (float)($meta['driver_fee_per_day'] ?? 2);

        if (!isset($data['price_total'])) {
            $pricing = $this->calcPricing(
                vehicle: $vehicle,
                days: (int)$meta['renter_days'],
                hasDriver: !empty($data['driver_id']),
                taxRate: $taxRate,
                driverFeePerDay: $driverFeePerDay
            );

            $data['price_subtotal']   = $data['price_subtotal']   ?? $pricing['price_subtotal'];
            $data['price_driver_fee'] = $data['price_driver_fee'] ?? $pricing['price_driver_fee'];
            $data['price_taxes']      = $data['price_taxes']      ?? $pricing['price_taxes'];
            $data['price_total']      = $pricing['price_total'];
        }

        $data['meta'] = $meta;

        // ✅ Create booking in transaction + generate code safely
        $booking = DB::transaction(function () use ($data) {
            $nextId = (Booking::lockForUpdate()->max('id') ?? 0) + 1;

            $data['code'] = 'SC-' . now()->format('Y') . '-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
            $data['status'] = $data['status'] ?? 'pending';
            $data['payment_status'] = $data['payment_status'] ?? 'unpaid';

            return Booking::create($data);
        });

        return response()->json(
            $booking->load(['customer.user','driver.user','vehicle','pickupLocation','dropoffLocation']),
            201
        );
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'vehicle_id'          => ['nullable','exists:vehicles,id'],
            'driver_id'           => ['nullable','exists:drivers,id'],

            'pickup_time'         => ['nullable','date'],
            'dropoff_time'        => ['nullable','date','after:pickup_time'],

            'pickup_location_id'  => ['nullable','exists:locations,id'],
            'dropoff_location_id' => ['nullable','exists:locations,id'],

            'currency'            => ['nullable','string','size:3'],
            'price_subtotal'      => ['nullable','numeric','min:0'],
            'price_driver_fee'    => ['nullable','numeric','min:0'],
            'price_taxes'         => ['nullable','numeric','min:0'],
            'price_total'         => ['nullable','numeric','min:0'],
            'meta'                => ['nullable','array'],
        ]);

        $meta = is_array($data['meta'] ?? null)
            ? $data['meta']
            : (is_array($booking->meta ?? null) ? $booking->meta : []);

        $pickupTime  = $data['pickup_time']  ?? $booking->pickup_time;
        $dropoffTime = $data['dropoff_time'] ?? $booking->dropoff_time;

        $days = $this->calcRentalDays($pickupTime, $dropoffTime);
        $meta['renter_days'] = $meta['renter_days'] ?? $days;

        if (!isset($data['price_total'])) {
            $vehicleId = $data['vehicle_id'] ?? $booking->vehicle_id;
            if ($vehicleId) {
                $vehicle = Vehicle::find($vehicleId);
                if ($vehicle) {
                    $taxRate = (float)($meta['tax_rate'] ?? 0.08);
                    $driverFeePerDay = (float)($meta['driver_fee_per_day'] ?? 2);

                    $pricing = $this->calcPricing(
                        vehicle: $vehicle,
                        days: (int)$meta['renter_days'],
                        hasDriver: !empty($data['driver_id'] ?? $booking->driver_id),
                        taxRate: $taxRate,
                        driverFeePerDay: $driverFeePerDay
                    );

                    $data['price_subtotal']   = $data['price_subtotal']   ?? $pricing['price_subtotal'];
                    $data['price_driver_fee'] = $data['price_driver_fee'] ?? $pricing['price_driver_fee'];
                    $data['price_taxes']      = $data['price_taxes']      ?? $pricing['price_taxes'];
                    $data['price_total']      = $pricing['price_total'];
                }
            }
        }

        $data['meta'] = $meta;

        $booking->update($data);

        return response()->json(
            $booking->load(['customer.user','driver.user','vehicle','pickupLocation','dropoffLocation'])
        );
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();
        return response()->json(['deleted' => true]);
    }

    public function assignDriver(Request $request, Booking $booking)
    {
        $data = $request->validate(['driver_id' => 'required|exists:drivers,id']);
        $booking->update(['driver_id' => $data['driver_id']]);
        return response()->json($booking->load('driver.user'));
    }

    public function setStatus(Request $request, Booking $booking)
    {
        $data = $request->validate(['status' => 'required|string']);
        $booking->update(['status' => $data['status']]);
        return response()->json($booking);
    }

    // ---------------------------
    // Helpers
    // ---------------------------

    /**
     * ✅ Generate a unique customer code BEFORE insert (fixes SQLSTATE 1364)
     */
    private function generateCustomerCode(): string
    {
        // Example: CUS-2026-7F3A1B2C
        return 'CUS-' . now()->format('Y') . '-' . strtoupper(Str::random(8));
    }

    /**
     * ✅ Resolve customer_id:
     * - if request provided customer_id, allow only admin-ish to use other customers
     * - else, find or create customer linked to auth user
     */
    private function resolveCustomerId(Request $request, ?int $customerIdFromRequest): int
    {
        $user = $request->user();
        if (!$user) {
            throw ValidationException::withMessages([
                'customer_id' => ['Unauthenticated.'],
            ]);
        }

        if ($customerIdFromRequest) {
            if (!$this->isAdminish($user)) {
                $cust = Customer::findOrFail($customerIdFromRequest);
                if (Schema::hasColumn('customers', 'user_id') && (int)$cust->user_id !== (int)$user->id) {
                    throw ValidationException::withMessages([
                        'customer_id' => ['You cannot book for another customer.'],
                    ]);
                }
            }
            return $customerIdFromRequest;
        }

        if (!Schema::hasColumn('customers', 'user_id')) {
            throw ValidationException::withMessages([
                'customer_id' => ['customers.user_id column not found. Please pass customer_id.'],
            ]);
        }

        $existing = Customer::where('user_id', $user->id)->first();
        if ($existing) return (int)$existing->id;

        // ✅ Create customer with REQUIRED FIELDS (code must exist)
        $payload = [
            'user_id' => $user->id,
        ];

        if (Schema::hasColumn('customers', 'code')) {
            $payload['code'] = $this->generateCustomerCode();
        }

        if (Schema::hasColumn('customers', 'status')) {
            $payload['status'] = 'active';
        }

        if (Schema::hasColumn('customers', 'preferences')) {
            $payload['preferences'] = null;
        }

        $created = Customer::create($payload);
        return (int)$created->id;
    }

    private function isAdminish($user): bool
    {
        if (!$user) return false;
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole(['admin', 'manager', 'agent', 'owner']);
        }
        return in_array((string)($user->role ?? ''), ['admin','manager','agent','owner'], true);
    }

    private function calcRentalDays($pickupTime, $dropoffTime): int
    {
        try {
            $start = Carbon::parse($pickupTime);
            $end = $dropoffTime ? Carbon::parse($dropoffTime) : null;

            if (!$end) return 1;

            $minutes = $start->diffInMinutes($end, false);
            if ($minutes <= 0) return 1;

            $days = (int) ceil($minutes / (60 * 24));
            return max(1, $days);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function calcPricing(Vehicle $vehicle, int $days, bool $hasDriver, float $taxRate, float $driverFeePerDay): array
    {
        $days = max(1, (int)$days);

        $daily =
            (float)($vehicle->base_daily_rate ?? 0) ?:
            (float)($vehicle->price_per_day ?? 0) ?:
            (float)($vehicle->daily_rate ?? 0) ?:
            (float)($vehicle->price ?? 0);

        $subtotal = $daily * $days;
        $driverFee = $hasDriver ? ($driverFeePerDay * $days) : 0.0;

        $taxable = $subtotal + $driverFee;
        $taxes = round($taxable * max(0, $taxRate), 2);

        return [
            'price_subtotal' => round($subtotal, 2),
            'price_driver_fee' => round($driverFee, 2),
            'price_taxes' => $taxes,
            'price_total' => round($subtotal + $driverFee + $taxes, 2),
        ];
    }
}
