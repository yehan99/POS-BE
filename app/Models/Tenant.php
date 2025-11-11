<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'business_type',
        'country',
        'phone',
        'settings',
        'is_active',
        'trial_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Users that belong to the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
