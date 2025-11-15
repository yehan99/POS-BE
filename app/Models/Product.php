<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'description',
        'brand',
        'barcode',
        'price',
        'cost_price',
        'tax_class',
        'is_active',
        'track_inventory',
        'stock_quantity',
        'reorder_level',
        'max_stock_level',
        'weight',
        'dimensions',
        'images',
        'variants',
        'attributes',
        'tags',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_class' => 'array',
        'is_active' => 'boolean',
        'track_inventory' => 'boolean',
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
        'max_stock_level' => 'integer',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'images' => 'array',
        'variants' => 'array',
        'attributes' => 'array',
        'tags' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
