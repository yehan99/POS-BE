<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * Roles that include this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission'
        )->withTimestamps();
    }

    /**
     * Users that have been assigned this permission explicitly.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_permissions'
        )->withTimestamps();
    }
}
