<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMatchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'driver_id', 'score', 'features', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'score'      => 'decimal:4',
            'features'   => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
