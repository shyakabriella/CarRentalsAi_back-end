<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Vehicle, Driver, Customer};
use App\Notifications\DriverBookingAssignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $q = Booking::with(['customer.user', 'driver.user', 'vehicle', 'pickupLocation', 'dropoffLocation'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $q->where('customer_id', $request->customer_id);
        }

        if ($request->filled('driver_id')) {
            $q->where('driver_id', $request->driver_id);
        }

        return response()->json($q->paginate(20));
    }

    /**
     * Return only the authenticated user's own bookings.
     * - customer => bookings where customer_id belongs to auth user
     * - driver   => bookings where driver_id belongs to auth user
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $q = Booking::with([
            'customer.user',
            'driver.user',
            'vehicle',
            'pickupLocation',
            'dropoffLocation',
            'payments',
        ])->orderBy('created_at', 'desc');

        if ($this->isDriverUser($user)) {
            if (!Schema::hasColumn('drivers', 'user_id')) {
                return response()->json([
                    'data' => [],
                    'message' => 'drivers.user_id column not found.',
                ]);
            }

            $driver = Driver::where('user_id', $user->id)->first();

            if (!$driver) {
                return response()->json([
                    'data' => [],
                    'message' => 'No driver profile found for this account.',
                ]);
            }

            $q->where('driver_id', $driver->id);
        } else {
            if (!Schema::hasColumn('customers', 'user_id')) {
                return response()->json([
                    'data' => [],
                    'message' => 'customers.user_id column not found.',
                ]);
            }

            $customer = Customer::where('user_id', $user->id)->first();

            if (!$customer) {
                return response()->json([
                    'data' => [],
                    'message' => 'No customer profile found for this account.',
                ]);
            }

            $q->where('customer_id', $customer->id);
        }

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return response()->json($q->paginate(20));
    }

    public function show(Booking $booking)
    {
        return response()->json(
            $booking->load(['customer.user', 'driver.user', 'vehicle', 'pickupLocation', 'dropoffLocation', 'payments'])
        );
    }

    /**
     * Car booking create (customer flow)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'         => ['nullable', 'exists:customers,id'],
            'vehicle_id'          => ['required', 'exists:vehicles,id'],
            'driver_id'           => ['nullable', 'exists:drivers,id'],

            'pickup_time'         => ['required', 'date'],
            'dropoff_time'        => ['nullable', 'date', 'after:pickup_time'],

            'pickup_location_id'  => ['nullable', 'exists:locations,id'],
            'dropoff_location_id' => ['nullable', 'exists:locations,id'],

            'pickup_address'      => ['nullable', 'string', 'max:255'],
            'dropoff_address'     => ['nullable', 'string', 'max:255'],

            'currency'            => ['nullable', 'string', 'size:3'],

            'price_subtotal'      => ['nullable', 'numeric', 'min:0'],
            'price_driver_fee'    => ['nullable', 'numeric', 'min:0'],
            'price_taxes'         => ['nullable', 'numeric', 'min:0'],
            'price_total'         => ['nullable', 'numeric', 'min:0'],

            'meta'                => ['nullable', 'array'],
        ]);

        $data['customer_id'] = $this->resolveCustomerId($request, $data['customer_id'] ?? null);

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        if (Schema::hasColumn('vehicles', 'status')) {
            $status = strtolower((string) ($vehicle->status ?? ''));
            if ($status && !in_array($status, ['available', 'in_service'], true)) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ["This vehicle is not available right now (status: {$status})."],
                ]);
            }
        }

        if (!empty($data['driver_id'])) {
            Driver::with('user')->findOrFail($data['driver_id']);
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if (!empty($data['pickup_address'])) {
            $meta['pickup_address'] = $data['pickup_address'];
        }

        if (!empty($data['dropoff_address'])) {
            $meta['dropoff_address'] = $data['dropoff_address'];
        }

        unset($data['pickup_address'], $data['dropoff_address']);

        $days = $this->calcRentalDays($data['pickup_time'], $data['dropoff_time'] ?? null);
        $meta['renter_days'] = $meta['renter_days'] ?? $days;

        $data['currency'] = $data['currency'] ?? 'RWF';
        $taxRate = (float) ($meta['tax_rate'] ?? 0.08);
        $driverFeePerDay = (float) ($meta['driver_fee_per_day'] ?? 2);

        if (!isset($data['price_total'])) {
            $pricing = $this->calcPricing(
                vehicle: $vehicle,
                days: (int) $meta['renter_days'],
                hasDriver: !empty($data['driver_id']),
                taxRate: $taxRate,
                driverFeePerDay: $driverFeePerDay
            );

            $data['price_subtotal']   = $data['price_subtotal'] ?? $pricing['price_subtotal'];
            $data['price_driver_fee'] = $data['price_driver_fee'] ?? $pricing['price_driver_fee'];
            $data['price_taxes']      = $data['price_taxes'] ?? $pricing['price_taxes'];
            $data['price_total']      = $pricing['price_total'];
        }

        $data['meta'] = $meta;

        $booking = DB::transaction(function () use ($data) {
            $nextId = (Booking::lockForUpdate()->max('id') ?? 0) + 1;

            $data['code'] = 'SC-' . now()->format('Y') . '-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            $data['status'] = $data['status'] ?? 'pending';
            $data['payment_status'] = $data['payment_status'] ?? 'unpaid';

            return Booking::create($data);
        });

        $booking->load(['customer.user', 'driver.user', 'vehicle', 'pickupLocation', 'dropoffLocation']);

        $this->notifyDriverIfAssigned($booking, 'new');

        return response()->json($booking, 201);
    }

    public function update(Request $request, Booking $booking)
    {
        $oldDriverId = $booking->driver_id;

        $data = $request->validate([
            'vehicle_id'          => ['nullable', 'exists:vehicles,id'],
            'driver_id'           => ['nullable', 'exists:drivers,id'],

            'pickup_time'         => ['nullable', 'date'],
            'dropoff_time'        => ['nullable', 'date', 'after:pickup_time'],

            'pickup_location_id'  => ['nullable', 'exists:locations,id'],
            'dropoff_location_id' => ['nullable', 'exists:locations,id'],

            'currency'            => ['nullable', 'string', 'size:3'],
            'price_subtotal'      => ['nullable', 'numeric', 'min:0'],
            'price_driver_fee'    => ['nullable', 'numeric', 'min:0'],
            'price_taxes'         => ['nullable', 'numeric', 'min:0'],
            'price_total'         => ['nullable', 'numeric', 'min:0'],
            'meta'                => ['nullable', 'array'],
        ]);

        if (array_key_exists('driver_id', $data) && !empty($data['driver_id'])) {
            Driver::with('user')->findOrFail($data['driver_id']);
        }

        $meta = is_array($data['meta'] ?? null)
            ? $data['meta']
            : (is_array($booking->meta ?? null) ? $booking->meta : []);

        $pickupTime  = $data['pickup_time'] ?? $booking->pickup_time;
        $dropoffTime = $data['dropoff_time'] ?? $booking->dropoff_time;

        $days = $this->calcRentalDays($pickupTime, $dropoffTime);
        $meta['renter_days'] = $meta['renter_days'] ?? $days;

        if (!isset($data['price_total'])) {
            $vehicleId = $data['vehicle_id'] ?? $booking->vehicle_id;
            if ($vehicleId) {
                $vehicle = Vehicle::find($vehicleId);
                if ($vehicle) {
                    $taxRate = (float) ($meta['tax_rate'] ?? 0.08);
                    $driverFeePerDay = (float) ($meta['driver_fee_per_day'] ?? 2);

                    $pricing = $this->calcPricing(
                        vehicle: $vehicle,
                        days: (int) $meta['renter_days'],
                        hasDriver: !empty($data['driver_id'] ?? $booking->driver_id),
                        taxRate: $taxRate,
                        driverFeePerDay: $driverFeePerDay
                    );

                    $data['price_subtotal']   = $data['price_subtotal'] ?? $pricing['price_subtotal'];
                    $data['price_driver_fee'] = $data['price_driver_fee'] ?? $pricing['price_driver_fee'];
                    $data['price_taxes']      = $data['price_taxes'] ?? $pricing['price_taxes'];
                    $data['price_total']      = $pricing['price_total'];
                }
            }
        }

        $data['meta'] = $meta;

        $booking->update($data);

        $booking->load(['customer.user', 'driver.user', 'vehicle', 'pickupLocation', 'dropoffLocation']);

        if (!empty($booking->driver_id) && (int) $booking->driver_id !== (int) $oldDriverId) {
            $this->notifyDriverIfAssigned($booking, 'updated');
        }

        return response()->json($booking);
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return response()->json(['deleted' => true]);
    }

    public function assignDriver(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
        ]);

        $oldDriverId = $booking->driver_id;

        Driver::with('user')->findOrFail($data['driver_id']);

        $booking->update([
            'driver_id' => $data['driver_id'],
        ]);

        $booking->load(['driver.user', 'customer.user', 'vehicle', 'pickupLocation', 'dropoffLocation']);

        if ((int) $booking->driver_id !== (int) $oldDriverId) {
            $this->notifyDriverIfAssigned($booking, 'assigned');
        }

        return response()->json($booking->load('driver.user'));
    }

    public function setStatus(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => 'required|string',
        ]);

        $booking->update([
            'status' => $data['status'],
        ]);

        return response()->json($booking);
    }

    // ---------------------------
    // Helpers
    // ---------------------------

    private function generateCustomerCode(): string
    {
        return 'CUS-' . now()->format('Y') . '-' . strtoupper(Str::random(8));
    }

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

                if (Schema::hasColumn('customers', 'user_id') && (int) $cust->user_id !== (int) $user->id) {
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
        if ($existing) {
            return (int) $existing->id;
        }

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

        return (int) $created->id;
    }

    private function isAdminish($user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole(['admin', 'manager', 'agent', 'owner']);
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'hasRole')) {
            foreach (['admin', 'manager', 'agent', 'owner'] as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        $role = strtolower((string) ($user->role ?? ''));
        $primaryRole = strtolower((string) ($user->primary_role ?? ''));

        if (in_array($role, ['admin', 'manager', 'agent', 'owner'], true)) {
            return true;
        }

        if (in_array($primaryRole, ['admin', 'manager', 'agent', 'owner'], true)) {
            return true;
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            foreach ($user->roles as $r) {
                $name = strtolower((string) ($r->name ?? $r->slug ?? $r ?? ''));
                if (in_array($name, ['admin', 'manager', 'agent', 'owner'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isDriverUser($user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole('driver')) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole(['driver'])) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        $role = strtolower((string) ($user->role ?? ''));
        $primaryRole = strtolower((string) ($user->primary_role ?? ''));

        if ($role === 'driver' || $primaryRole === 'driver') {
            return true;
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            foreach ($user->roles as $r) {
                $name = strtolower((string) ($r->name ?? $r->slug ?? $r ?? ''));
                if ($name === 'driver') {
                    return true;
                }
            }
        }

        return false;
    }

    private function calcRentalDays($pickupTime, $dropoffTime): int
    {
        try {
            $start = Carbon::parse($pickupTime);
            $end = $dropoffTime ? Carbon::parse($dropoffTime) : null;

            if (!$end) {
                return 1;
            }

            $minutes = $start->diffInMinutes($end, false);
            if ($minutes <= 0) {
                return 1;
            }

            $days = (int) ceil($minutes / (60 * 24));

            return max(1, $days);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function calcPricing(Vehicle $vehicle, int $days, bool $hasDriver, float $taxRate, float $driverFeePerDay): array
    {
        $days = max(1, (int) $days);

        $daily =
            (float) ($vehicle->base_daily_rate ?? 0) ?:
            (float) ($vehicle->price_per_day ?? 0) ?:
            (float) ($vehicle->daily_rate ?? 0) ?:
            (float) ($vehicle->price ?? 0);

        $subtotal = $daily * $days;
        $driverFee = $hasDriver ? ($driverFeePerDay * $days) : 0.0;

        $taxable = $subtotal + $driverFee;
        $taxes = round($taxable * max(0, $taxRate), 2);

        return [
            'price_subtotal'   => round($subtotal, 2),
            'price_driver_fee' => round($driverFee, 2),
            'price_taxes'      => $taxes,
            'price_total'      => round($subtotal + $driverFee + $taxes, 2),
        ];
    }

    private function notifyDriverIfAssigned(Booking $booking, string $context = 'assigned'): void
    {
        if (!$booking->driver_id) {
            return;
        }

        $booking->loadMissing([
            'driver.user',
            'customer.user',
            'vehicle',
            'pickupLocation',
            'dropoffLocation',
        ]);

        $driverEmail = $booking->driver->user->email
            ?? $booking->driver->email
            ?? null;

        $driverName = $booking->driver->user->name
            ?? $booking->driver->name
            ?? 'Driver';

        if (!$driverEmail) {
            Log::warning('Driver notification skipped because email was not found.', [
                'booking_id' => $booking->id,
                'driver_id' => $booking->driver_id,
            ]);
            return;
        }

        try {
            Notification::route('mail', $driverEmail)
                ->notify(new DriverBookingAssignedNotification($booking, $context));
        } catch (\Throwable $e) {
            Log::error('Failed to send driver booking notification.', [
                'booking_id' => $booking->id,
                'driver_id' => $booking->driver_id,
                'driver_email' => $driverEmail,
                'driver_name' => $driverName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}