<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /** Optional: keep in sync with controller Rule::in(...) */
    public const STATUSES = ['available','in_service','booked','maintenance','inactive'];

    protected $fillable = [
        'user_id',
        'vehicle_type_id', 'plate_no', 'vin', 'make', 'model', 'year', 'seats',
        'fuel_type', 'transmission', 'odometer_km', 'base_daily_rate',
        'base_hourly_rate', 'status', 'location_id', 'media',

        // SPA/showroom convenience (mapped via accessors)
        'license_plate',   // -> plate_no
        'price_per_day',   // -> base_daily_rate
        'image_url',
    ];

    protected $casts = [
        'year'             => 'integer',
        'seats'            => 'integer',
        'odometer_km'      => 'integer',
        'base_daily_rate'  => 'decimal:2',
        'base_hourly_rate' => 'decimal:2',
        'media'            => 'array',
        'price_per_day'    => 'decimal:2',
    ];

    protected $appends = [
        'license_plate',
        'price_per_day',
        'image_url',
        'display_name',
    ];

    /* =========================
     * Normalizers (mutators)
     * ========================= */

    protected function plateNo(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null
                ? strtoupper(preg_replace('/\s+/', '', (string) $v))
                : null
        );
    }

    protected function vin(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null ? strtoupper(trim((string) $v)) : null
        );
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            set: function ($v) {
                $v = is_string($v) ? strtolower($v) : $v;
                return in_array($v, self::STATUSES, true) ? $v : ($this->attributes['status'] ?? 'available');
            }
        );
    }

    /* =========================
     * Attribute Aliases (SPA ↔ DB)
     * ========================= */

    protected function licensePlate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['license_plate'] ?? $this->attributes['plate_no'] ?? null,
            set: fn ($value) => [
                'plate_no' => strtoupper(preg_replace('/\s+/', '', (string) $value)),
            ],
        );
    }


    public function driver()
    {
        return $this->hasOne(Driver::class, 'vehicle_id');
    }

    protected function pricePerDay(): Attribute
    {
        return Attribute::make(
            get: fn () => array_key_exists('price_per_day', $this->attributes)
                ? $this->attributes['price_per_day']
                : ($this->attributes['base_daily_rate'] ?? null),
            set: fn ($value) => [
                'base_daily_rate' => $value,
                'price_per_day'   => $value,
            ],
        );
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $attr = $this->attributes['image_url'] ?? null;
                if (!empty($attr)) return $attr;

                if ($this->relationLoaded('images')) {
                    $img = $this->images->sortByDesc('is_primary')->first();
                    return $img?->image_url;
                }

                $img = $this->images()
                    ->orderByDesc('is_primary')
                    ->orderByDesc('id')
                    ->first();

                return $img?->image_url;
            },
            set: fn ($value) => ['image_url' => $value],
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = array_filter([
                    $this->year,
                    $this->make ? Str::title($this->make) : null,
                    $this->model ? Str::upper($this->model) : null,
                ]);
                return $parts ? implode(' ', $parts) : ($this->plate_no ?? 'Vehicle');
            }
        );
    }

    /* =========================
     * Relationships
     * ========================= */

    public function type()      { return $this->belongsTo(VehicleType::class, 'vehicle_type_id'); }
    public function location()  { return $this->belongsTo(Location::class); }
    public function bookings()  { return $this->hasMany(Booking::class); }
    public function maintenanceRecords() { return $this->hasMany(MaintenanceRecord::class); }
    public function images()    { return $this->hasMany(ImageGenerator::class, 'vehicle_id'); }
    public function user()      { return $this->belongsTo(User::class); }

    /* =========================
     * Scopes
     * ========================= */

    public function scopeAvailable($q)                 { return $q->where('status', 'available'); }
    public function scopeAtLocation($q, $locationId)   { return $q->where('location_id', $locationId); }
    public function scopeOwnedBy($q, $userId)          { return $q->where('user_id', $userId); }

    public function scopeStatus($q, $statuses)
    {
        $list = array_values(array_intersect((array) $statuses, self::STATUSES));
        return $list ? $q->whereIn('status', $list) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $q;
        return $q->where(function ($s) use ($term) {
            $s->where('plate_no', 'like', "%{$term}%")
              ->orWhere('make', 'like', "%{$term}%")
              ->orWhere('model', 'like', "%{$term}%");
        });
    }

    public function scopeYearBetween($q, ?int $min = null, ?int $max = null)
    {
        if ($min !== null) $q->where('year', '>=', $min);
        if ($max !== null) $q->where('year', '<=', $max);
        return $q;
    }

    public function scopeRateBetween($q, ?float $min = null, ?float $max = null)
    {
        if ($min !== null) $q->where('base_daily_rate', '>=', $min);
        if ($max !== null) $q->where('base_daily_rate', '<=', $max);
        return $q;
    }
}
