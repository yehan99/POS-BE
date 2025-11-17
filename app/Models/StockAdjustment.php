<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockAdjustment extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

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
        'adjustment_number',
        'product_id',
        'product_name',
        'product_sku',
        'location_id',
        'adjustment_type',
        'quantity',
        'quantity_change',
        'previous_stock',
        'new_stock',
        'reason',
        'notes',
        'unit_cost',
        'total_value',
        'value_change',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejection_reason',
        'rejected_at',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'quantity_change' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
        'value_change' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Scope adjustments by status.
     */
    public function scopeStatus(Builder $builder, string $status): Builder
    {
        return $builder->where('status', $status);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public static function generateNumber(): string
    {
        $prefix = 'SA-' . now()->format('Y');

        do {
            $sequence = Str::upper(Str::random(5));
            $number = $prefix . '-' . $sequence;
        } while (static::withTrashed()->where('adjustment_number', $number)->exists());

        return $number;
    }
}
