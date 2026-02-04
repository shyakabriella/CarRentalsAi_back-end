<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vehicle_id',   // ✅ ADD THIS

        // Profile
        'profile_image',
        'gender',
        'marital_status',

        // License
        'license_no',
        'license_expiry',
        'license_category',
        'experience_years',

        // Location (Google Map)
        'current_lat',
        'current_lng',
        'current_address',
        'location_updated_at',

        // Status & availability
        'status',
        'is_verified',
        'is_available',

        // Ratings / stats
        'rating_avg',
        'rating_count',
        'cancel_count',

        // Extra data
        'profile',
    ];

    protected $appends = [
        'profile_image_url', // ✅ helpful for frontend
    ];

    protected function casts(): array
    {
        return [
            'license_expiry'        => 'date',
            'experience_years'      => 'integer',

            'current_lat'           => 'decimal:7',
            'current_lng'           => 'decimal:7',
            'location_updated_at'   => 'datetime',

            'is_verified'           => 'boolean',
            'is_available'          => 'boolean',

            'rating_avg'            => 'decimal:2',
            'rating_count'          => 'integer',
            'cancel_count'          => 'integer',

            'profile'               => 'array',
        ];
    }

    /* ---------------- Relationships ---------------- */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle() // ✅ THIS FIXES YOUR ERROR
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }

    public function reviews()
    {
        return $this->hasMany(DriverReview::class, 'driver_id');
    }

    public function availabilities()
    {
        return $this->hasMany(DriverAvailability::class, 'driver_id');
    }

    /* ---------------- Accessors ---------------- */

    public function getProfileImageUrlAttribute(): ?string
    {
        if (!$this->profile_image) return null;

        // if already full url stored
        if (str_starts_with($this->profile_image, 'http')) return $this->profile_image;

        // normalize "\" to "/"
        $clean = str_replace('\\', '/', ltrim($this->profile_image, '/'));

        return asset('storage/' . $clean);
    }

    /* ---------------- Helpful Scopes ---------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function updateLocation(float $lat, float $lng, ?string $address = null): void
    {
        $this->current_lat = $lat;
        $this->current_lng = $lng;
        if ($address !== null) $this->current_address = $address;

        $this->location_updated_at = now();
        $this->save();
    }
}