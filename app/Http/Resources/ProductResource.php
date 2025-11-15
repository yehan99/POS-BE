<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => (function () use ($request) {
                $category = $this->relationLoaded('category')
                    ? $this->category
                    : ($this->category_id ? $this->category()->first() : null);

                return $category
                    ? ProductCategoryResource::make($category)->toArray($request)
                    : null;
            })(),
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'price' => (float) $this->price,
            'costPrice' => (float) $this->cost_price,
            'taxClass' => $this->tax_class,
            'isActive' => $this->is_active,
            'trackInventory' => $this->track_inventory,
            'stockQuantity' => (int) $this->stock_quantity,
            'reorderLevel' => $this->reorder_level,
            'maxStockLevel' => $this->max_stock_level,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'dimensions' => $this->dimensions,
            'images' => $this->images ?? [],
            'variants' => $this->variants ?? [],
            'attributes' => $this->attributes ?? [],
            'tags' => $this->tags ?? [],
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
