<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /** Spatie should use web guard */
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    protected $appends = [
        'roles_list',
        'primary_role',
    ];

    /* -------------------- Relationships -------------------- */

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    /* -------------------- Role helpers -------------------- */

    public function getRolesListAttribute(): array
    {
        return $this->getRoleNames()->values()->toArray();
    }

    public function getPrimaryRoleAttribute(): ?string
    {
        // Prefer plain DB column
        $column = $this->attributes['role'] ?? null;
        if (!empty($column)) return $column;

        // fallback spatie
        return $this->getRoleNames()->first() ?: null;
    }
}
