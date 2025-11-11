<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthToken extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'name',
        'access_token_id',
        'refresh_token_hash',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'ip_address',
        'user_agent',
        'revoked',
        'last_used_at',
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'revoked' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Owning user for the token pair.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
