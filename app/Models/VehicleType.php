<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'attributes'];

    // Cast JSON attributes column to array so API returns it as an object
    protected $casts = [
        'attributes' => 'array',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
