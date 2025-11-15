<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'loyalty_points',
        'loyalty_tier',
        'total_purchases',
        'total_spent',
        'last_purchase_at',
        'notes',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'address' => 'array',
        'date_of_birth' => 'date',
        'last_purchase_at' => 'datetime',
        'is_active' => 'boolean',
        'loyalty_points' => 'integer',
        'total_purchases' => 'integer',
        'total_spent' => 'decimal:2',
    ];

    /**
     * Generate a unique customer code.
     */
    public static function generateCode(): string
    {
        do {
            $code = 'CUST-' . Str::upper(Str::random(6));
        } while (static::withTrashed()->where('customer_code', $code)->exists());

        return $code;
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyTransaction::class);
    }
}
