<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\ReorderProductCategoriesRequest;
use App\Http\Requests\Product\StoreProductCategoryRequest;
use App\Http\Requests\Product\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));
        $limit = (int) $request->input('limit', 20);
        $limit = $limit > 0 ? min($limit, 100) : 20;
        $page = max((int) $request->input('page', 1), 1);

        $baseQuery = ProductCategory::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId));

        $metricsBaseQuery = clone $baseQuery;
        $totalCategories = (clone $metricsBaseQuery)->count();
        $activeCategories = (clone $metricsBaseQuery)->where('is_active', true)->count();
        $highestSortOrder = (clone $metricsBaseQuery)->max('sort_order');

        $query = clone $baseQuery;

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        } elseif ($request->filled('isActive')) {
            $query->where('is_active', filter_var($request->input('isActive'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('parentId')) {
            $parentId = $request->input('parentId');
            if ($parentId === null || $parentId === '' || $parentId === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        $sortable = [
            'name' => 'name',
            'sortOrder' => 'sort_order',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
        ];

        $sortBy = $request->input('sortBy', 'sortOrder');
        $sortOrder = strtolower($request->input('sortOrder', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortColumn = $sortable[$sortBy] ?? 'sort_order';

        $query->orderBy($sortColumn, $sortOrder)
            ->orderBy('name');

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        $categories = collect($paginator->items())
            ->map(fn (ProductCategory $category) => ProductCategoryResource::make($category)->toArray($request))
            ->all();

        return response()->json([
            'data' => $categories,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'hasNext' => $paginator->hasMorePages(),
                'hasPrev' => $paginator->currentPage() > 1,
            ],
            'metrics' => [
                'total' => $totalCategories,
                'active' => $activeCategories,
                'inactive' => max(0, $totalCategories - $activeCategories),
                'nextSortOrder' => max(1, (int) $highestSortOrder + 1),
            ],
        ]);
    }

    public function store(StoreProductCategoryRequest $request): ProductCategoryResource
    {
        $data = $request->validated();
        $tenantId = $this->resolveTenantId($data['tenantId'] ?? null);

        $category = ProductCategory::create([
            'tenant_id' => $tenantId,
            'parent_id' => $data['parentId'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sortOrder'] ?? 0,
            'is_active' => $data['isActive'] ?? true,
            'image' => $data['image'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return ProductCategoryResource::make($category);
    }

    public function show(ProductCategory $category): ProductCategoryResource
    {
        return ProductCategoryResource::make($category);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $category): ProductCategoryResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($category, $data) {
            if (Arr::has($data, 'name')) {
                $category->name = $data['name'];
                $category->slug = ProductCategory::generateSlug($category->name, $category->id);
            }

            if (Arr::exists($data, 'description')) {
                $category->description = $data['description'];
            }

            if (Arr::exists($data, 'parentId')) {
                $category->parent_id = $data['parentId'];
            }

            if (Arr::exists($data, 'sortOrder')) {
                $category->sort_order = $data['sortOrder'];
            }

            if (Arr::exists($data, 'isActive')) {
                $category->is_active = (bool) $data['isActive'];
            }

            if (Arr::exists($data, 'image')) {
                $category->image = $data['image'];
            }

            if (Arr::exists($data, 'metadata')) {
                $category->metadata = $data['metadata'];
            }
            $category->save();
        });

        return ProductCategoryResource::make($category->fresh());
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(null, 204);
    }

    public function active(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $categories = ProductCategory::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(
            ProductCategoryResource::collection($categories)->toArray($request)
        );
    }

    public function tree(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->input('tenantId'));

        $categories = ProductCategory::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $tree = $this->buildTree($categories);

        return response()->json($tree);
    }

    public function reorder(ReorderProductCategoriesRequest $request): JsonResponse
    {
        $categoryIds = $request->validated('categoryIds');

        DB::transaction(function () use ($categoryIds) {
            foreach ($categoryIds as $index => $id) {
                ProductCategory::where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['updated' => count($categoryIds)]);
    }

    private function buildTree(Collection $categories, ?string $parentId = null): array
    {
        return $categories
            ->where('parent_id', $parentId)
            ->map(fn (ProductCategory $category) => [
                ...ProductCategoryResource::make($category)->resolve(),
                'children' => $this->buildTree($categories, $category->id),
            ])
            ->values()
            ->all();
    }

    private function resolveTenantId(?string $tenantId): ?string
    {
        if ($tenantId) {
            return $tenantId;
        }

        return Tenant::query()->value('id');
    }
}
