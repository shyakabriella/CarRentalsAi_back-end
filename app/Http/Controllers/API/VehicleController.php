<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class VehicleController extends Controller
{
    /** Allowed lifecycle statuses for vehicles */
    private const STATUSES = ['available','in_service','booked','maintenance','inactive'];

    /** Whether vehicles table has user_id (for ownership) */
    private function hasOwnerColumn(): bool
    {
        static $has = null;
        if ($has === null) $has = Schema::hasColumn('vehicles', 'user_id');
        return $has;
    }

    /** Role helpers (spatie-compatible; safe fallback) */
    private function isAgent(): bool
    {
        $u = auth()->user();
        if (!$u) return false;
        if (method_exists($u, 'hasRole')) return $u->hasRole('agent');
        return (optional($u->role)->slug ?? null) === 'agent';
    }

    private function isAdminish(): bool
    {
        $u = auth()->user();
        if (!$u) return false;
        if (method_exists($u, 'hasAnyRole')) return $u->hasAnyRole(['admin','manager']);
        return in_array(optional($u->role)->slug, ['admin','manager'], true);
    }

    /**
     * Enforce ownership only if:
     *  - vehicles.user_id column exists
     *  - caller is agent
     *  - and the vehicle is ALREADY assigned (user_id not null)
     * Agents may edit UNASSIGNED vehicles (user_id = null).
     */
    private function authorizeOwnershipIfEnabled(Vehicle $vehicle): void
    {
        if ($this->hasOwnerColumn() && $this->isAgent() && !empty($vehicle->user_id)) {
            if ((int) $vehicle->user_id !== (int) auth()->id()) {
                abort(403, 'You are not allowed to modify this vehicle.');
            }
        }
    }

    /** Map SPA aliases → DB fields before validation */
    private function normalizeAliases(Request $request): void
    {
        if ($request->filled('license_plate') && !$request->filled('plate_no')) {
            $request->merge(['plate_no' => $request->input('license_plate')]);
        }
        if ($request->filled('price_per_day') && !$request->filled('base_daily_rate')) {
            $request->merge(['base_daily_rate' => $request->input('price_per_day')]);
        }
    }

    /* =========================
     * Authenticated (list/detail)
     * ========================= */

    public function index(Request $request)
    {
        $perPage  = (int) max(1, min((int) $request->input('per_page', 20), 100));
        $sortable = ['id','year','base_daily_rate','created_at'];
        $sort     = in_array($request->get('sort'), $sortable, true) ? $request->get('sort') : 'id';
        $dir      = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $q = Vehicle::query()->with(['type','location']);

        // show only mine if ?mine=1 (when user_id exists)
        if ($this->hasOwnerColumn() && $request->boolean('mine') && auth()->check()) {
            $q->where('user_id', auth()->id());
        }

        // Optional owner filter for admins
        if ($this->hasOwnerColumn() && $this->isAdminish() && $request->filled('user_id')) {
            $q->where('user_id', (int) $request->input('user_id'));
        }

        // Filters
        if ($request->filled('status')) {
            $statuses = (array) $request->input('status');
            $valid = array_values(array_intersect($statuses, self::STATUSES));
            if (!empty($valid)) $q->whereIn('status', $valid);
        }

        // treat these as plain values (static ids or codes)
        if ($request->filled('location_id')) {
            $q->where('location_id', $request->input('location_id'));
        }
        if ($request->filled('vehicle_type_id')) {
            $q->where('vehicle_type_id', $request->input('vehicle_type_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $q->where(function ($s) use ($term) {
                $s->where('plate_no', 'like', "%{$term}%")
                  ->orWhere('make', 'like', "%{$term}%")
                  ->orWhere('model', 'like', "%{$term}%");
            });
        }
        if ($request->filled('year_min'))  $q->where('year', '>=', (int) $request->input('year_min'));
        if ($request->filled('year_max'))  $q->where('year', '<=', (int) $request->input('year_max'));
        if ($request->filled('rate_min'))  $q->where('base_daily_rate', '>=', (float) $request->input('rate_min'));
        if ($request->filled('rate_max'))  $q->where('base_daily_rate', '<=', (float) $request->input('rate_max'));

        $q->orderBy($sort, $dir);

        return response()->json(
            $q->paginate($perPage)->appends($request->query())
        );
    }

    public function show(Vehicle $vehicle)
    {
        $this->authorizeOwnershipIfEnabled($vehicle);
        return response()->json($vehicle->load(['type','location']));
    }

    /* =========================
     * Authenticated (write)
     * ========================= */

   public function store(Request $request)
    {
        $this->normalizeAliases($request);

        $rules = [
            // UPDATE: Added 'integer' and 'exists' to validate against the DB table.
            // This fixes the "selected vehicle type id is invalid" error,
            // PROVIDED your 'vehicle_types' database table has these IDs populate.
            'vehicle_type_id'  => ['required', 'integer', 'exists:vehicle_types,id'],

            'plate_no'         => ['required','string','max:30', Rule::unique('vehicles','plate_no')],
            'vin'              => ['nullable','string','max:60', Rule::unique('vehicles','vin')],
            'make'             => ['nullable','string','max:80'],
            'model'            => ['nullable','string','max:80'],
            'year'             => ['nullable','integer','min:1980','max:'.(date('Y')+1)],
            'seats'            => ['nullable','integer','min:1','max:20'],
            'fuel_type'        => ['nullable','string','max:30'],
            'transmission'     => ['nullable','string','max:30'],
            'odometer_km'      => ['nullable','integer','min:0'],
            'base_daily_rate'  => ['nullable','numeric','min:0'],
            'base_hourly_rate' => ['nullable','numeric','min:0'],
            'status'           => ['nullable','string', Rule::in(self::STATUSES)],

            // UPDATE: Added validation for location_id as well
            'location_id'      => ['nullable', 'integer', 'exists:locations,id'],

            'media'            => ['nullable','array'],
            // Aliases (optional - handled by model mutators)
            'license_plate'    => ['sometimes','string','max:30'],
            'price_per_day'    => ['sometimes','numeric','min:0'],
            'image_url'        => ['sometimes','string','max:255'],
        ];

        if ($this->hasOwnerColumn() && $this->isAdminish()) {
            $rules['user_id'] = ['sometimes','integer','exists:users,id'];
        }

        $data = $request->validate($rules);

        // If ownership exists and user is agent, force ownership
        if ($this->hasOwnerColumn() && $this->isAgent()) {
            // Ensure the agent ID is an integer
            $data['user_id'] = (int) auth()->id();
        }

        if (empty($data['status'])) $data['status'] = 'available';

        $vehicle = Vehicle::create($data)->load(['type','location']);
        return response()->json($vehicle, 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorizeOwnershipIfEnabled($vehicle);
        $this->normalizeAliases($request);

        $rules = [
            // same idea on update
            'vehicle_type_id'  => ['sometimes'],

            'plate_no'         => ['sometimes','string','max:30', Rule::unique('vehicles','plate_no')->ignore($vehicle->id)],
            'vin'              => ['nullable','string','max:60', Rule::unique('vehicles','vin')->ignore($vehicle->id)],
            'make'             => ['nullable','string','max:80'],
            'model'            => ['nullable','string','max:80'],
            'year'             => ['nullable','integer','min:1980','max:'.(date('Y')+1)],
            'seats'            => ['nullable','integer','min:1','max:20'],
            'fuel_type'        => ['nullable','string','max:30'],
            'transmission'     => ['nullable','string','max:30'],
            'odometer_km'      => ['nullable','integer','min:0'],
            'base_daily_rate'  => ['nullable','numeric','min:0'],
            'base_hourly_rate' => ['nullable','numeric','min:0'],
            'status'           => ['nullable','string', Rule::in(self::STATUSES)],

            'location_id'      => ['nullable'],

            'media'            => ['nullable','array'],
            // Aliases on update too
            'license_plate'    => ['sometimes','string','max:30'],
            'price_per_day'    => ['sometimes','numeric','min:0'],
            'image_url'        => ['sometimes','string','max:255'],
        ];

        if ($this->hasOwnerColumn() && $this->isAdminish()) {
            $rules['user_id'] = ['sometimes','integer','exists:users,id'];
        }

        $data = $request->validate($rules);

        // Agents cannot change owner
        if ($this->hasOwnerColumn() && $this->isAgent()) {
            unset($data['user_id']);
        }

        $vehicle->update($data);

        return response()->json($vehicle->fresh()->load(['type','location']));
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorizeOwnershipIfEnabled($vehicle);
        $vehicle->delete();
        return response()->json(['deleted' => true]);
    }

    public function setStatus(Request $request, Vehicle $vehicle)
    {
        $this->authorizeOwnershipIfEnabled($vehicle);

        $data = $request->validate([
            'status' => ['required','string', Rule::in(self::STATUSES)],
        ]);

        $vehicle->update(['status' => $data['status']]);

        return response()->json($vehicle->fresh());
    }

    /* =========================
     * PUBLIC (no auth)
     * ========================= */

    public function publicIndex(Request $request)
    {
        $perPage  = (int) max(1, min((int) $request->input('per_page', 24), 100));
        $sortable = ['id','year','base_daily_rate','created_at'];
        $sort     = in_array($request->get('sort'), $sortable, true) ? $request->get('sort') : 'created_at';
        $dir      = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $q = Vehicle::query()
            ->with(['type','location'])
            ->whereIn('status', ['available','in_service']);

        if ($request->filled('status')) {
            $statuses = (array) $request->input('status');
            $valid = array_values(array_intersect($statuses, self::STATUSES));
            if (!empty($valid)) $q->whereIn('status', $valid);
        }

        if ($request->filled('location_id')) {
            $q->where('location_id', $request->input('location_id'));
        }
        if ($request->filled('vehicle_type_id')) {
            $q->where('vehicle_type_id', $request->input('vehicle_type_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $q->where(function ($s) use ($term) {
                $s->where('plate_no', 'like', "%{$term}%")
                  ->orWhere('make', 'like', "%{$term}%")
                  ->orWhere('model', 'like', "%{$term}%");
            });
        }
        if ($request->filled('year_min'))  $q->where('year', '>=', (int) $request->input('year_min'));
        if ($request->filled('year_max'))  $q->where('year', '<=', (int) $request->input('year_max'));
        if ($request->filled('rate_min'))  $q->where('base_daily_rate', '>=', (float) $request->input('rate_min'));
        if ($request->filled('rate_max'))  $q->where('base_daily_rate', '<=', (float) $request->input('rate_max'));

        $q->orderBy($sort, $dir);

        return response()->json(
            $q->limit($perPage)->get()
        );
    }

    public function publicShow(Vehicle $vehicle)
    {
        return response()->json($vehicle->load(['type','location']));
    }

    public function publicPrimaryImage(Vehicle $vehicle)
    {
        return response()->json(['image_url' => $vehicle->image_url]);
    }
}
