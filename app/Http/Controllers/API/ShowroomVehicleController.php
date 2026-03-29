<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class ShowroomVehicleController extends Controller
{
    private const STATUSES = ['available', 'in_service', 'booked', 'maintenance', 'inactive'];

    private function hasOwnerColumn(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasColumn('vehicles', 'user_id');
        }

        return $has;
    }

    private function isAdminish(): bool
    {
        $u = auth()->user();
        if (!$u) {
            return false;
        }

        if (method_exists($u, 'hasAnyRole')) {
            return $u->hasAnyRole(['admin', 'manager']);
        }

        return in_array(optional($u->role)->slug, ['admin', 'manager'], true);
    }

    private function authorizeOwned(Vehicle $vehicle): void
    {
        if (!$this->hasOwnerColumn()) {
            return;
        }

        // Admin/manager can access any
        if ($this->isAdminish()) {
            return;
        }

        $uid = (int) ($vehicle->user_id ?? 0);

        if ($uid !== 0 && $uid !== (int) auth()->id()) {
            abort(403, 'Not your vehicle.');
        }
    }

    private function authorizeShowroomAccess(int $showroom): void
    {
        if (!$this->hasOwnerColumn()) {
            return;
        }

        if ($this->isAdminish()) {
            return;
        }

        if ((int) auth()->id() !== $showroom) {
            abort(403, 'Not allowed to view this showroom.');
        }
    }

    private function normalize(Request $request): void
    {
        if ($request->filled('license_plate') && !$request->filled('plate_no')) {
            $request->merge([
                'plate_no' => $request->input('license_plate'),
            ]);
        }

        if ($request->filled('price_per_day') && !$request->filled('base_daily_rate')) {
            $request->merge([
                'base_daily_rate' => $request->input('price_per_day'),
            ]);
        }
    }

    public function index(Request $request)
    {
        $q = Vehicle::query()->with(['type', 'location']);

        if ($this->hasOwnerColumn() && !$this->isAdminish()) {
            $q->where('user_id', auth()->id());
        }

        if ($this->hasOwnerColumn() && $this->isAdminish() && $request->filled('user_id')) {
            $q->where('user_id', (int) $request->input('user_id'));
        }

        return response()->json(
            $q->orderByDesc('id')->paginate(20)->appends($request->query())
        );
    }

    /**
     * Alias route support:
     * GET /api/showroom/{showroom}/vehicles
     */
    public function indexByShowroom(Request $request, int $showroom)
    {
        $this->authorizeShowroomAccess($showroom);

        $q = Vehicle::query()->with(['type', 'location']);

        if ($this->hasOwnerColumn()) {
            $q->where('user_id', $showroom);
        }

        return response()->json(
            $q->orderByDesc('id')->paginate(20)->appends($request->query())
        );
    }

    public function show(Vehicle $vehicle)
    {
        $this->authorizeOwned($vehicle);

        return response()->json($vehicle->load(['type', 'location']));
    }

    /**
     * Alias route support:
     * GET /api/showroom/{showroom}/vehicles/{vehicle}
     */
    public function showByShowroom(int $showroom, Vehicle $vehicle)
    {
        $this->authorizeShowroomAccess($showroom);

        if ($this->hasOwnerColumn()) {
            $vehicleOwnerId = (int) ($vehicle->user_id ?? 0);

            if ($vehicleOwnerId !== $showroom) {
                abort(404, 'Vehicle not found in this showroom.');
            }
        }

        return response()->json($vehicle->load(['type', 'location']));
    }

    public function store(Request $request)
    {
        $this->normalize($request);

        $rules = [
            'vehicle_type_id'  => ['required', 'exists:vehicle_types,id'],
            'plate_no'         => ['required', 'string', 'max:30', Rule::unique('vehicles', 'plate_no')],
            'vin'              => ['nullable', 'string', 'max:60', Rule::unique('vehicles', 'vin')],
            'make'             => ['nullable', 'string', 'max:80'],
            'model'            => ['nullable', 'string', 'max:80'],
            'year'             => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'seats'            => ['nullable', 'integer', 'min:1', 'max:20'],
            'fuel_type'        => ['nullable', 'string', 'max:30'],
            'transmission'     => ['nullable', 'string', 'max:30'],
            'odometer_km'      => ['nullable', 'integer', 'min:0'],
            'base_daily_rate'  => ['nullable', 'numeric', 'min:0'],
            'base_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'string', Rule::in(self::STATUSES)],
            'location_id'      => ['nullable', 'exists:locations,id'],
            'media'            => ['nullable', 'array'],
            'license_plate'    => ['sometimes', 'string', 'max:30'],
            'price_per_day'    => ['sometimes', 'numeric', 'min:0'],
            'image_url'        => ['sometimes', 'string', 'max:255'],
        ];

        if ($this->hasOwnerColumn() && $this->isAdminish()) {
            $rules['user_id'] = ['sometimes', 'integer', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        if ($this->hasOwnerColumn() && !$this->isAdminish()) {
            $data['user_id'] = auth()->id();
        }

        if (empty($data['status'])) {
            $data['status'] = 'available';
        }

        $v = Vehicle::create($data);

        return response()->json($v->load(['type', 'location']), 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorizeOwned($vehicle);
        $this->normalize($request);

        $rules = [
            'vehicle_type_id'  => ['sometimes', 'exists:vehicle_types,id'],
            'plate_no'         => ['sometimes', 'string', 'max:30', Rule::unique('vehicles', 'plate_no')->ignore($vehicle->id)],
            'vin'              => ['nullable', 'string', 'max:60', Rule::unique('vehicles', 'vin')->ignore($vehicle->id)],
            'make'             => ['nullable', 'string', 'max:80'],
            'model'            => ['nullable', 'string', 'max:80'],
            'year'             => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'seats'            => ['nullable', 'integer', 'min:1', 'max:20'],
            'fuel_type'        => ['nullable', 'string', 'max:30'],
            'transmission'     => ['nullable', 'string', 'max:30'],
            'odometer_km'      => ['nullable', 'integer', 'min:0'],
            'base_daily_rate'  => ['nullable', 'numeric', 'min:0'],
            'base_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'string', Rule::in(self::STATUSES)],
            'location_id'      => ['nullable', 'exists:locations,id'],
            'media'            => ['nullable', 'array'],
            'license_plate'    => ['sometimes', 'string', 'max:30'],
            'price_per_day'    => ['sometimes', 'numeric', 'min:0'],
            'image_url'        => ['sometimes', 'string', 'max:255'],
        ];

        if ($this->hasOwnerColumn() && $this->isAdminish()) {
            $rules['user_id'] = ['sometimes', 'integer', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        if ($this->hasOwnerColumn() && !$this->isAdminish()) {
            unset($data['user_id']);
        }

        $vehicle->update($data);

        return response()->json($vehicle->fresh()->load(['type', 'location']));
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorizeOwned($vehicle);
        $vehicle->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Claim an unassigned vehicle (user_id must be null).
     * On success, sets user_id = current user.
     */
    public function claim(Vehicle $vehicle)
    {
        if (!$this->hasOwnerColumn()) {
            abort(400, 'Ownership not supported.');
        }

        if (!empty($vehicle->user_id)) {
            abort(409, 'Vehicle is already assigned.');
        }

        $vehicle->update([
            'user_id' => auth()->id(),
        ]);

        return response()->json($vehicle->fresh()->load(['type', 'location']));
    }
}