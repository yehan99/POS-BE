<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\BulkDeleteProductsRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));
        $limit = (int) $request->input('limit', 20);
        $limit = $limit > 0 ? min($limit, 100) : 20;
        $page = max((int) $request->input('page', 1), 1);

        $query = Product::query()->with('category')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('categoryId')) {
            $query->where('category_id', $categoryId);
        }

        if ($brand = $request->input('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->filled('minPrice')) {
            $query->where('price', '>=', (float) $request->input('minPrice'));
        }

        if ($request->filled('maxPrice')) {
            $query->where('price', '<=', (float) $request->input('maxPrice'));
        }

        if ($request->filled('isActive')) {
            $query->where('is_active', filter_var($request->input('isActive'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('inStock')) {
            $inStock = filter_var($request->input('inStock'), FILTER_VALIDATE_BOOLEAN);
            if ($inStock) {
                $query->where('stock_quantity', '>', 0);
            } else {
                $query->where('stock_quantity', '<=', 0);
            }
        }

        $sortable = [
            'name' => 'name',
            'price' => 'price',
            'createdAt' => 'created_at',
            'stockQuantity' => 'stock_quantity',
            'sku' => 'sku',
        ];

        $sortBy = $request->input('sortBy', 'name');
        $sortOrder = strtolower($request->input('sortOrder', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortable[$sortBy] ?? 'name', $sortOrder);

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        $products = collect($paginator->items())
            ->map(fn (Product $product) => ProductResource::make($product->loadMissing('category'))->toArray($request))
            ->all();

        return response()->json([
            'data' => $products,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'hasNext' => $paginator->hasMorePages(),
                'hasPrev' => $paginator->currentPage() > 1,
            ],
        ]);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $data = $request->validated();
        $tenantId = $this->resolveTenantId($data['tenantId'] ?? null);

        $product = Product::create([
            'tenant_id' => $tenantId,
            'category_id' => $data['categoryId'] ?? null,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'brand' => $data['brand'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'price' => $data['price'],
            'cost_price' => $data['costPrice'] ?? null,
            'tax_class' => $data['taxClassData'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'track_inventory' => $data['trackInventory'] ?? true,
            'stock_quantity' => $data['stockQuantity'] ?? 0,
            'reorder_level' => $data['reorderLevel'] ?? null,
            'max_stock_level' => $data['maxStockLevel'] ?? null,
            'weight' => $data['weight'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'images' => $this->filterImages($data['images'] ?? []),
            'variants' => $data['variants'] ?? [],
            'attributes' => $data['attributes'] ?? [],
            'tags' => $this->normalizeTags($data['tags'] ?? []),
        ]);

        return ProductResource::make($product->fresh('category'));
    }

    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product->load('category'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($product, $data) {
            $fillableMap = [
                'categoryId' => 'category_id',
                'sku' => 'sku',
                'name' => 'name',
                'description' => 'description',
                'brand' => 'brand',
                'barcode' => 'barcode',
                'price' => 'price',
                'costPrice' => 'cost_price',
                'taxClassData' => 'tax_class',
                'isActive' => 'is_active',
                'trackInventory' => 'track_inventory',
                'stockQuantity' => 'stock_quantity',
                'reorderLevel' => 'reorder_level',
                'maxStockLevel' => 'max_stock_level',
                'weight' => 'weight',
                'dimensions' => 'dimensions',
            ];

            foreach ($fillableMap as $input => $attribute) {
                if (Arr::exists($data, $input)) {
                    $value = $data[$input];

                    if ($attribute === 'tax_class') {
                        $product->{$attribute} = $value ?: null;
                        continue;
                    }

                    if ($attribute === 'price' || $attribute === 'cost_price') {
                        $product->{$attribute} = $value !== null ? (float) $value : null;
                        continue;
                    }

                    if (in_array($attribute, ['is_active', 'track_inventory'], true)) {
                        $product->{$attribute} = (bool) $value;
                        continue;
                    }

                    $product->{$attribute} = $value;
                }
            }

            if (Arr::exists($data, 'images')) {
                $product->images = $this->filterImages($data['images']);
            }

            if (Arr::exists($data, 'variants')) {
                $product->variants = $data['variants'] ?? [];
            }

            if (Arr::exists($data, 'attributes')) {
                $product->attributes = $data['attributes'] ?? [];
            }

            if (Arr::exists($data, 'tags')) {
                $product->tags = $this->normalizeTags($data['tags'] ?? []);
            }

            $product->save();
        });

        return ProductResource::make($product->fresh('category'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    public function bulkDelete(BulkDeleteProductsRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');
        $deleted = Product::whereIn('id', $ids)->delete();

        return response()->json(['deleted' => $deleted]);
    }

    public function updateStock(UpdateProductStockRequest $request, Product $product): ProductResource|JsonResponse
    {
        if (!$product->track_inventory) {
            return response()->json([
                'message' => 'Inventory tracking is disabled for this product.',
            ], 422);
        }

        $quantity = (int) $request->validated('quantity');
        $operation = $request->validated('operation');

        $newQuantity = match ($operation) {
            'add' => $product->stock_quantity + $quantity,
            'subtract' => max(0, $product->stock_quantity - $quantity),
            'set' => $quantity,
        };

        $product->update(['stock_quantity' => $newQuantity]);

        return ProductResource::make($product->fresh('category'));
    }

    public function lowStock(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $products = Product::query()
            ->with('category')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('track_inventory', true)
            ->whereNotNull('reorder_level')
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->orderBy('stock_quantity')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products)->toArray($request),
        ]);
    }

    public function outOfStock(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $products = Product::query()
            ->with('category')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products)->toArray($request),
        ]);
    }

    public function generateSku(Request $request): JsonResponse
    {
        $categoryId = $request->input('categoryId');
        $prefix = 'SKU';

        if ($categoryId && ($category = ProductCategory::find($categoryId))) {
            $prefix = Str::upper(Str::slug($category->name, '')) ?: $prefix;
        }

        $sku = $this->makeUniqueSku($prefix);

        return response()->json(['sku' => $sku]);
    }

    public function generateBarcode(): JsonResponse
    {
        $barcode = $this->makeUniqueBarcode();

        return response()->json(['barcode' => $barcode]);
    }

    public function checkSku(Request $request): JsonResponse
    {
        $sku = $request->input('sku');
        $excludeId = $request->input('excludeId');

        $exists = Product::withTrashed()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('sku', $sku)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    public function checkBarcode(Request $request): JsonResponse
    {
        $barcode = $request->input('barcode');
        $excludeId = $request->input('excludeId');

        $exists = Product::withTrashed()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('barcode', $barcode)
            ->whereNotNull('barcode')
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $file = $request->file('image');
        $path = $file->store('products/'.$product->id, 'public');
        $url = Storage::disk('public')->url($path);

        $images = $product->images ?? [];
        $images[] = $url;
        $product->images = array_values(array_unique($images));
        $product->save();

        return response()->json(['imageUrl' => $url]);
    }

    public function deleteImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'imageUrl' => ['required', 'string'],
        ]);

        $imageUrl = $request->input('imageUrl');
        $images = $product->images ?? [];
        $updated = array_values(array_filter($images, fn ($image) => $image !== $imageUrl));

        if (count($updated) !== count($images)) {
            $product->images = $updated;
            $product->save();

            $storagePrefix = Storage::disk('public')->url('');
            if ($storagePrefix && str_starts_with($imageUrl, $storagePrefix)) {
                $path = Str::after($imageUrl, $storagePrefix);
                Storage::disk('public')->delete($path);
            } elseif (str_starts_with($imageUrl, '/storage/')) {
                $path = Str::after($imageUrl, '/storage/');
                Storage::disk('public')->delete($path);
            }
        }

        return response()->json(['removed' => count($images) - count($updated)]);
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $header = null;
        $success = 0;
        $failed = 0;
        $errors = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($header === null) {
                $header = array_map(fn ($value) => Str::of($value)->trim()->toString(), $row);
                continue;
            }

            if (count($row) === 1 && $row[0] === null) {
                continue;
            }

            $data = array_combine($header, $row);

            if (!$data) {
                $failed++;
                $errors[] = 'Row could not be parsed.';
                continue;
            }

            $sku = Str::upper(trim($data['sku'] ?? ''));
            $name = trim($data['name'] ?? '');
            $price = isset($data['price']) ? (float) $data['price'] : null;

            if (!$sku || !$name || $price === null) {
                $failed++;
                $errors[] = "Missing required fields for SKU {$sku}";
                continue;
            }

            try {
                Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $name,
                        'price' => $price,
                        'tenant_id' => $this->resolveTenantId(null),
                        'description' => $data['description'] ?? null,
                        'brand' => $data['brand'] ?? null,
                        'barcode' => $data['barcode'] ?? null,
                        'is_active' => ($data['is_active'] ?? 'true') !== 'false',
                        'track_inventory' => ($data['track_inventory'] ?? 'true') !== 'false',
                        'stock_quantity' => isset($data['stock_quantity']) ? (int) $data['stock_quantity'] : 0,
                    ]
                );
                $success++;
            } catch (\Throwable $throwable) {
                $failed++;
                $errors[] = "Failed to import SKU {$sku}: {$throwable->getMessage()}";
            }
        }

        fclose($handle);

        return response()->json([
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));
        $filters = $request->only(['search', 'categoryId', 'isActive']);

        $callback = function () use ($tenantId, $filters) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'sku', 'name', 'price', 'brand', 'barcode', 'stock_quantity', 'is_active', 'track_inventory', 'category_name',
            ]);

            $query = Product::query()->with('category')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

            if ($search = Arr::get($filters, 'search')) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if ($categoryId = Arr::get($filters, 'categoryId')) {
                $query->where('category_id', $categoryId);
            }

            if (Arr::has($filters, 'isActive')) {
                $query->where('is_active', filter_var($filters['isActive'], FILTER_VALIDATE_BOOLEAN));
            }

            $query->orderBy('name')
                ->chunk(200, function ($products) use ($handle) {
                    foreach ($products as $product) {
                        fputcsv($handle, [
                            $product->sku,
                            $product->name,
                            $product->price,
                            $product->brand,
                            $product->barcode,
                            $product->stock_quantity,
                            $product->is_active ? 'true' : 'false',
                            $product->track_inventory ? 'true' : 'false',
                            $product->category?->name,
                        ]);
                    }
                });

            fclose($handle);
        };

        $filename = 'products_'.now()->format('Y_m_d_His').'.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $query = Product::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $totalProducts = (clone $query)->count();
        $activeProducts = (clone $query)->where('is_active', true)->count();
        $inactiveProducts = $totalProducts - $activeProducts;
        $lowStockProducts = (clone $query)
            ->where('track_inventory', true)
            ->whereNotNull('reorder_level')
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->count();
        $outOfStockProducts = (clone $query)
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->count();
        $totalStockValue = (clone $query)
            ->selectRaw('COALESCE(SUM(price * stock_quantity), 0) as value')
            ->value('value');
        $averageProductPrice = (clone $query)->avg('price') ?: 0;

        $categoryCounts = ProductCategory::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->withCount(['products' => fn ($relation) => $relation->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))])
            ->orderByDesc('products_count')
            ->limit(10)
            ->get()
            ->map(fn ($category) => [
                'categoryId' => $category->id,
                'categoryName' => $category->name,
                'count' => $category->products_count,
            ]);

        return response()->json([
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'inactiveProducts' => $inactiveProducts,
            'lowStockProducts' => $lowStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'totalStockValue' => (float) $totalStockValue,
            'averageProductPrice' => (float) $averageProductPrice,
            'categoryCounts' => $categoryCounts,
        ]);
    }

    public function brands(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $brands = Product::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values();

        return response()->json(['brands' => $brands]);
    }

    public function tags(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $tags = Product::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values();

        return response()->json(['tags' => $tags]);
    }

    private function filterImages(array $images): array
    {
        return array_values(array_filter($images, fn ($image) => is_string($image) && !str_starts_with($image, 'data:')));
    }

    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->filter(fn ($tag) => is_string($tag) && $tag !== '')
            ->map(fn ($tag) => Str::of($tag)->trim()->toString())
            ->unique()
            ->values()
            ->all();
    }

    private function makeUniqueSku(string $prefix): string
    {
        $prefix = Str::upper(preg_replace('/[^A-Z0-9]/', '', $prefix));
        $prefix = $prefix ?: 'SKU';

        do {
            $sku = $prefix.'-'.Str::upper(Str::random(6));
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    private function makeUniqueBarcode(): string
    {
        do {
            $barcode = (string) random_int(1000000000000, 9999999999999);
        } while (Product::withTrashed()->where('barcode', $barcode)->exists());

        return $barcode;
    }

    private function resolveTenantId(?string $tenantId): ?string
    {
        if ($tenantId) {
            return $tenantId;
        }

        return Tenant::query()->value('id');
    }
}
