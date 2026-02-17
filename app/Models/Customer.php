<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'document_no',
        'preferences',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(DriverReview::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * ✅ Ensure a Customer row exists for a given User.
     * - returns existing (restores if soft-deleted)
     * - creates if missing
     * - can also update document_no/preferences/status if provided
     */
    public static function ensureForUser(User $user, array $attrs = []): self
    {
        $q = static::withTrashed()->where('user_id', $user->id);
        $customer = $q->first();

        if ($customer && $customer->trashed()) {
            $customer->restore();
        }

        if (!$customer) {
            $payload = [
                'user_id' => $user->id,
                'status' => $attrs['status'] ?? 'active',
            ];

            if (array_key_exists('document_no', $attrs))  $payload['document_no'] = $attrs['document_no'];
            if (array_key_exists('preferences', $attrs))  $payload['preferences'] = $attrs['preferences'];

            $customer = static::create($payload);
        } else {
            // update optional fields if passed
            $updates = [];
            if (array_key_exists('document_no', $attrs)) $updates['document_no'] = $attrs['document_no'];
            if (array_key_exists('preferences', $attrs)) $updates['preferences'] = $attrs['preferences'];
            if (array_key_exists('status', $attrs)) $updates['status'] = $attrs['status'];
            if (!empty($updates)) $customer->update($updates);
        }

        // ✅ generate code if empty
        if (empty($customer->code)) {
            $customer->update([
                'code' => 'CUS-' . now()->format('Y') . '-' . str_pad((string)$customer->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        return $customer->fresh();
    }
}
