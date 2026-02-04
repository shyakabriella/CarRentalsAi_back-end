<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverAvailability extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'driver_id',
        'starts_at',
        'ends_at',
        'status',   // available | busy | off
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'meta'      => 'array',
        ];
    }

    /* ---------------- Relationships ---------------- */

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /* ---------------- Helpful Scopes ---------------- */

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
}