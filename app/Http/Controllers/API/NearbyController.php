<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NearbyController extends Controller
{
    /**
     * Haversine SQL formula (KM)
     */
    private function haversineKm(float $lat, float $lng, string $latCol, string $lngCol): string
    {
        return "(6371 * acos(
            cos(radians($lat)) * cos(radians($latCol)) *
            cos(radians($lngCol) - radians($lng)) +
            sin(radians($lat)) * sin(radians($latCol))
        ))";
    }

    /**
     * Detect location columns on "locations" table.
     * Supports: (latitude, longitude) OR (lat, lng)
     */
    private function detectLocationColumns(): array
    {
        if (Schema::hasColumn('locations', 'latitude') && Schema::hasColumn('locations', 'longitude')) {
            return ['latitude', 'longitude'];
        }

        if (Schema::hasColumn('locations', 'lat') && Schema::hasColumn('locations', 'lng')) {
            return ['lat', 'lng'];
        }

        return [null, null];
    }

    /**
     * Validate lat/lng query params
     */
    private function validateLatLng(Request $request): array
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if ($lat === null || $lng === null) {
            return [null, null];
        }

        return [(float) $lat, (float) $lng];
    }

    /**
     * Apply active filter only if column exists.
     */
    private function applyActiveFilter($query, string $table)
    {
        if (Schema::hasColumn($table, 'is_active')) {
            return $query->where("$table.is_active", 1);
        }

        if (Schema::hasColumn($table, 'active')) {
            return $query->where("$table.active", 1);
        }

        if (Schema::hasColumn($table, 'status')) {
            return $query->where("$table.status", 'active');
        }

        if (Schema::hasColumn($table, 'availability')) {
            return $query->where("$table.availability", 'available');
        }

        return $query;
    }

    /**
     * Apply soft-delete filter only if deleted_at exists
     */
    private function applyNotDeleted($query, string $table)
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            return $query->whereNull("$table.deleted_at");
        }
        return $query;
    }

    /**
     * GET /api/public/nearby/cars?lat=...&lng=...&radius=10
     */
    public function cars(Request $request)
    {
        [$lat, $lng] = $this->validateLatLng($request);
        if ($lat === null || $lng === null) {
            return response()->json([
                "success" => false,
                "message" => "lat and lng are required"
            ], 422);
        }

        $radius = (float) $request->query('radius', 10);

        [$latField, $lngField] = $this->detectLocationColumns();
        if (!$latField || !$lngField) {
            return response()->json([
                "success" => false,
                "message" => "locations table has no coordinates. Add (latitude, longitude) OR (lat, lng)."
            ], 500);
        }

        $latCol = "locations.$latField";
        $lngCol = "locations.$lngField";

        $distanceSql = $this->haversineKm($lat, $lng, $latCol, $lngCol);

        $q = Vehicle::query()
            ->select('vehicles.*')
            ->selectRaw("$distanceSql as distance_km")
            ->join('locations', 'locations.id', '=', 'vehicles.location_id')
            ->whereNotNull($latCol)
            ->whereNotNull($lngCol);

        // ✅ filters
        $q = $this->applyActiveFilter($q, 'vehicles');
        $q = $this->applyNotDeleted($q, 'vehicles');

        // ✅ recommended: show only available/in_service if vehicles.status exists
        if (Schema::hasColumn('vehicles', 'status')) {
            $q->whereIn('vehicles.status', ['available', 'in_service']);
        }

        $cars = $q->having('distance_km', '<=', $radius)
            ->orderBy('distance_km')
            ->limit(30)
            ->get();

        return response()->json([
            "success" => true,
            "data" => $cars
        ]);
    }

    /**
     * ✅ Drivers use drivers.current_lat + drivers.current_lng (NO JOIN on locations)
     * ✅ Also load user so frontend shows name
     *
     * GET /api/public/nearby/drivers?lat=...&lng=...&radius=10&only_available=1
     */
    public function drivers(Request $request)
    {
        [$lat, $lng] = $this->validateLatLng($request);
        if ($lat === null || $lng === null) {
            return response()->json([
                "success" => false,
                "message" => "lat and lng are required"
            ], 422);
        }

        $radius = (float) $request->query('radius', 10);

        // ✅ ensure driver location columns exist
        if (!Schema::hasColumn('drivers', 'current_lat') || !Schema::hasColumn('drivers', 'current_lng')) {
            return response()->json([
                "success" => false,
                "message" => "drivers table must have current_lat and current_lng columns."
            ], 500);
        }

        $latCol = "drivers.current_lat";
        $lngCol = "drivers.current_lng";

        $distanceSql = $this->haversineKm($lat, $lng, $latCol, $lngCol);

        $q = Driver::query()
            ->select('drivers.*')
            ->selectRaw("$distanceSql as distance_km")
            ->whereNotNull($latCol)
            ->whereNotNull($lngCol)
            // ✅ SUPER IMPORTANT: include user for name
            ->with(['user:id,name,phone,email']);

        // ✅ filters
        $q = $this->applyActiveFilter($q, 'drivers');
        $q = $this->applyNotDeleted($q, 'drivers');

        // ✅ optional: only available drivers
        if ($request->boolean('only_available') && Schema::hasColumn('drivers', 'is_available')) {
            $q->where('drivers.is_available', 1);
        }

        $drivers = $q->having('distance_km', '<=', $radius)
            ->orderBy('distance_km')
            ->limit(30)
            ->get();

        return response()->json([
            "success" => true,
            "data" => $drivers
        ]);
    }

    /**
     * GET /api/public/nearby/location?lat=...&lng=...
     */
    public function location(Request $request)
    {
        [$lat, $lng] = $this->validateLatLng($request);
        if ($lat === null || $lng === null) {
            return response()->json([
                "success" => false,
                "message" => "lat and lng are required"
            ], 422);
        }

        [$latField, $lngField] = $this->detectLocationColumns();
        if (!$latField || !$lngField) {
            return response()->json([
                "success" => true,
                "data" => ["name" => "Your location"]
            ]);
        }

        $latCol = "locations.$latField";
        $lngCol = "locations.$lngField";

        $distanceSql = $this->haversineKm($lat, $lng, $latCol, $lngCol);

        $nearest = Location::query()
            ->select('locations.*')
            ->selectRaw("$distanceSql as distance_km")
            ->whereNotNull($latCol)
            ->whereNotNull($lngCol)
            ->orderBy('distance_km')
            ->first();

        return response()->json([
            "success" => true,
            "data" => [
                "name" => $nearest?->name ?? "Your location"
            ]
        ]);
    }
}