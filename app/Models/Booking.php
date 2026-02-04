<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'pickup_time'      => 'datetime',
            'dropoff_time'     => 'datetime',
            'price_subtotal'   => 'decimal:2',
            'price_driver_fee' => 'decimal:2',
            'price_taxes'      => 'decimal:2',
            'price_total'      => 'decimal:2',
            'pricing_snapshot' => 'array',
            'meta'             => 'array',
        ];
    }

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

    /* Scopes */
    public function scopeStatus($q, string $status)
    {
        return $q->where('status', $status);
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['pending','confirmed','in_progress']);
    }
}
