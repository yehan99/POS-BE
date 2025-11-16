<?php

namespace App\Models;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
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
        'po_number',
        'supplier_id',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'tax',
        'discount',
        'shipping_cost',
        'total',
        'expected_delivery_date',
        'actual_delivery_date',
        'approved_at',
        'ordered_at',
        'received_at',
        'cancelled_at',
        'cancel_reason',
        'created_by',
        'approved_by',
        'received_by',
        'notes',
        'terms_and_conditions',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Generate a unique purchase order number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'PO-' . now()->format('Y');

        do {
            $sequence = Str::upper(Str::random(5));
            $number = $prefix . '-' . $sequence;
        } while (static::withTrashed()->where('po_number', $number)->exists());

        return $number;
    }

    /**
     * Scope orders by status convenience helper.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Supplier relationship.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Determine the current lead time in days.
     */
    public function leadTimeDays(): ?float
    {
        if (! $this->received_at) {
            return null;
        }

        $start = $this->ordered_at ?? $this->created_at;
        if (! $start) {
            return null;
        }

        $diff = $start->diffInSeconds($this->received_at);
        $interval = CarbonInterval::seconds($diff);

        return round($interval->totalDays, 2);
    }
}
