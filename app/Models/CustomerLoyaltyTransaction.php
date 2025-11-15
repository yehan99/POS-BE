<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyTransaction extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'type',
        'points_delta',
        'points_balance',
        'total_spent_delta',
        'total_spent_balance',
        'purchases_delta',
        'purchases_balance',
        'reason',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'total_spent_delta' => 'decimal:2',
        'total_spent_balance' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    protected $table = 'customer_loyalty_transactions';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
