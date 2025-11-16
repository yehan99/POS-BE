<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockTransfer extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

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
        'transfer_number',
        'from_location_id',
        'to_location_id',
        'status',
        'total_items',
        'total_value',
        'requested_by',
        'approved_by',
        'shipped_by',
        'received_by',
        'notes',
        'approved_at',
        'shipped_at',
        'received_at',
        'cancelled_at',
        'cancel_reason',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_items' => 'integer',
        'total_value' => 'decimal:2',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Scope transfers by status.
     */
    public function scopeStatus(Builder $builder, string $status): Builder
    {
        return $builder->where('status', $status);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public static function generateNumber(): string
    {
        $prefix = 'ST-' . now()->format('Y');

        do {
            $sequence = Str::upper(Str::random(5));
            $number = $prefix . '-' . $sequence;
        } while (static::withTrashed()->where('transfer_number', $number)->exists());

        return $number;
    }
}
