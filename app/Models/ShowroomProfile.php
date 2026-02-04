<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowroomProfile extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'lat',
        'lng',
        'logo_path',
        'working_permission_pdf_path',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    protected $appends = [
        'logo_url',
        'working_permission_pdf_url',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) return null;
        return asset('storage/' . ltrim(str_replace('\\', '/', $this->logo_path), '/'));
    }

    public function getWorkingPermissionPdfUrlAttribute(): ?string
    {
        if (!$this->working_permission_pdf_path) return null;
        return asset('storage/' . ltrim(str_replace('\\', '/', $this->working_permission_pdf_path), '/'));
    }
}