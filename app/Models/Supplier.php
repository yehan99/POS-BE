<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
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
        'supplier_code',
        'name',
        'contact_person',
        'email',
        'phone',
        'category',
        'status',
        'is_active',
        'is_preferred',
        'payment_terms',
        'credit_limit',
        'tax_id',
        'website',
        'address',
        'bank_details',
        'notes',
        'rating',
        'total_purchases',
        'total_orders',
        'total_spent',
        'spend_this_month',
        'spend_last_month',
        'on_time_delivery_rate',
        'average_lead_time_days',
        'last_purchase_at',
        'monthly_spend_stats',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'address' => 'array',
        'bank_details' => 'array',
        'is_active' => 'boolean',
        'is_preferred' => 'boolean',
        'credit_limit' => 'decimal:2',
        'rating' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'spend_this_month' => 'decimal:2',
        'spend_last_month' => 'decimal:2',
        'on_time_delivery_rate' => 'decimal:2',
        'average_lead_time_days' => 'decimal:2',
        'last_purchase_at' => 'datetime',
        'monthly_spend_stats' => 'array',
    ];

    /**
     * Generate a unique supplier code.
     */
    public static function generateCode(): string
    {
        do {
            $code = 'SUP-' . Str::upper(Str::random(6));
        } while (static::withTrashed()->where('supplier_code', $code)->exists());

        return $code;
    }

    /**
     * Ensure the status flag reflects the active state.
     */
    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            if ($supplier->status === null) {
                $supplier->status = $supplier->is_active ? 'active' : 'inactive';
            }

            if ($supplier->status === 'active') {
                $supplier->is_active = true;
            } elseif ($supplier->status === 'inactive') {
                $supplier->is_active = false;
            }
        });
    }
}
