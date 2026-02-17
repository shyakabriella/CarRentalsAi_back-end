<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'customer_id', 'vehicle_id', 'driver_id',
        'pickup_time', 'dropoff_time', 'pickup_location_id', 'dropoff_location_id',
        'status', 'payment_status', 'currency',
        'price_subtotal', 'price_driver_fee', 'price_taxes', 'price_total',
        'pricing_snapshot', 'meta',
    ];

    /**
     * ✅ Defaults (so bookings always have consistent initial values)
     */
    protected $attributes = [
        'status'         => 'pending',
        'payment_status' => 'unpaid',
        'currency'       => 'RWF',
    ];

    /**
     * ✅ Auto-append computed attributes to JSON responses
     */
    protected $appends = [
        'renter_days',
        'has_driver',
    ];

    protected function casts(): array
    {
        return [
            'pickup_time'      => 'datetime',
            'dropoff_time'     => 'datetime',
            'status'           => 'string',
            'payment_status'   => 'string',
            'currency'         => 'string',

            'price_subtotal'   => 'decimal:2',
            'price_driver_fee' => 'decimal:2',
            'price_taxes'      => 'decimal:2',
            'price_total'      => 'decimal:2',

            'pricing_snapshot' => 'array',
            'meta'             => 'array',
        ];
    }

    // -----------------------------------
    // Relationships
    // -----------------------------------

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dropoffLocation()
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews()
    {
        return $this->hasMany(DriverReview::class);
    }

    public function matchLogs()
    {
        return $this->hasMany(AiMatchLog::class);
    }

    // -----------------------------------
    // Computed attributes (very useful for frontend)
    // -----------------------------------

    public function getHasDriverAttribute(): bool
    {
        return !empty($this->driver_id);
    }

    /**
     * ✅ Days between pickup and dropoff (min 1)
     * - Uses meta.renter_days if set (from controller)
     * - Otherwise calculates from pickup/dropoff_time
     */
    public function getRenterDaysAttribute(): int
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        if (!empty($meta['renter_days'])) {
            return max(1, (int) $meta['renter_days']);
        }

        try {
            $start = $this->pickup_time ? Carbon::parse($this->pickup_time) : null;
            $end   = $this->dropoff_time ? Carbon::parse($this->dropoff_time) : null;

            if (!$start || !$end) return 1;

            $minutes = $start->diffInMinutes($end, false);
            if ($minutes <= 0) return 1;

            $days = (int) ceil($minutes / (60 * 24));
            return max(1, $days);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * ✅ Ensure pricing_snapshot is always an array (safe for frontend)
     */
    public function getPricingSnapshotAttribute($value)
    {
        $decoded = is_array($value) ? $value : (json_decode($value ?? '[]', true) ?: []);
        return is_array($decoded) ? $decoded : [];
    }

    // -----------------------------------
    // Scopes
    // -----------------------------------

    public function scopeStatus($q, string $status)
    {
        return $q->where('status', $status);
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['pending', 'confirmed', 'in_progress']);
    }
}
