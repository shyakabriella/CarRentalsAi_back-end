<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageGenerator extends Model
{
    // Table name you created in the migration
    protected $table = 'imagegenerator';

    // Allow the fields the controller is creating/updating
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'source',       // 'upload' | 'generate'
        'image_path',   // storage path in 'public' disk
        'image_url',    // public URL
        'thumb_url',
        'is_primary',
        'prompt',
        'seed',
        'style',
        'params',       // JSON
        'status',       // e.g. 'succeeded'
        'error',        // nullable error text
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'params'     => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
