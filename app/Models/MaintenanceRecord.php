<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_id', 'type', 'title', 'notes', 'odometer_km', 'cost',
        'scheduled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'odometer_km' => 'integer',
            'cost'        => 'decimal:2',
            'scheduled_at'=> 'datetime',
            'completed_at'=> 'datetime',
        ];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
