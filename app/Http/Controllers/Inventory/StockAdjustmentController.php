<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustment\BulkStoreStockAdjustmentRequest;
use App\Http\Requests\Inventory\StockAdjustment\RejectStockAdjustmentRequest;
use App\Http\Requests\Inventory\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockAdjustmentController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 10;

    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', self::DEFAULT_PAGE_SIZE);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : self::DEFAULT_PAGE_SIZE;

        $query = StockAdjustment::query()->with(['product', 'location']);
        $this->applyFilters($query, $request);

        $sortable = [
            'adjustmentNumber' => 'adjustment_number',
            'quantity' => 'quantity',
            'status' => 'status',
            'createdAt' => 'created_at',
            'totalValue' => 'total_value',
            'productName' => 'product_name',
        ];

        $sortBy = (string) $request->input('sortBy', 'createdAt');
        $sortOrder = strtolower((string) $request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        $column = $sortable[$sortBy] ?? 'created_at';
        $query->orderBy($column, $sortOrder);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $collection = StockAdjustmentResource::collection($paginator->getCollection());

        return response()->json([
            'data' => $collection->toArray($request),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'hasNext' => $paginator->hasMorePages(),
                'hasPrev' => $paginator->currentPage() > 1,
            ],
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'totalPages' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request): StockAdjustmentResource
    {
        $userName = $request->user()?->name ?? 'System';

        $adjustment = DB::transaction(function () use ($request, $userName) {
            return $this->createAdjustment($request->validated(), $userName);
        });

        return StockAdjustmentResource::make($adjustment);
    }

    public function show(StockAdjustment $adjustment): StockAdjustmentResource
    {
        return StockAdjustmentResource::make($adjustment->load(['product', 'location']));
    }

    public function approve(Request $request, StockAdjustment $adjustment): StockAdjustmentResource
    {
        if ($adjustment->status !== StockAdjustment::STATUS_PENDING) {
            abort(422, 'Only pending adjustments can be approved.');
        }

        $userName = $request->user()?->name ?? 'System';

        DB::transaction(function () use ($adjustment, $userName) {
            $product = Product::query()->lockForUpdate()->find($adjustment->product_id);
            $currentStock = $product?->stock_quantity ?? 0;
            $change = (int) $adjustment->quantity_change;

            if ($change < 0 && abs($change) > $currentStock) {
                $change = -$currentStock;
            }

            $quantity = abs($change);
            $unitCost = (float) $adjustment->unit_cost;
            $newStock = $currentStock + $change;
            if ($newStock < 0) {
                $newStock = 0;
            }

            $adjustment->previous_stock = $currentStock;
            $adjustment->quantity = $quantity;
            $adjustment->quantity_change = $change;
            $adjustment->new_stock = $newStock;
            $adjustment->total_value = $quantity * $unitCost;
            $adjustment->value_change = $change * $unitCost;
            $adjustment->status = StockAdjustment::STATUS_APPROVED;
            $adjustment->approved_at = now();
            $adjustment->approved_by = $userName;
            $adjustment->rejected_at = null;
            $adjustment->rejected_by = null;
            $adjustment->rejection_reason = null;
            $adjustment->save();

            if ($product) {
                $product->stock_quantity = $newStock;
                $product->save();
            }
        });

        return StockAdjustmentResource::make($adjustment->fresh(['product', 'location']));
    }

    public function reject(RejectStockAdjustmentRequest $request, StockAdjustment $adjustment): StockAdjustmentResource
    {
        if ($adjustment->status !== StockAdjustment::STATUS_PENDING) {
            abort(422, 'Only pending adjustments can be rejected.');
        }

        $userName = $request->user()?->name ?? 'System';
        $reason = $request->validated()['reason'];

        $adjustment->status = StockAdjustment::STATUS_REJECTED;
        $adjustment->rejected_at = now();
        $adjustment->rejected_by = $userName;
        $adjustment->rejection_reason = $reason;
        $adjustment->save();

        return StockAdjustmentResource::make($adjustment->fresh(['product', 'location']));
    }

    public function bulkStore(BulkStoreStockAdjustmentRequest $request): JsonResponse
    {
        $userName = $request->user()?->name ?? 'System';

        $created = DB::transaction(function () use ($request, $userName) {
            return collect($request->validated()['adjustments'])
                ->map(fn (array $payload) => $this->createAdjustment($payload, $userName));
        });

        return response()->json([
            'data' => StockAdjustmentResource::collection($created)->toArray($request),
            'count' => $created->count(),
        ], 201);
    }

    public function export(Request $request): StreamedResponse
    {
        $fileName = 'stock_adjustments_' . now()->format('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $filters = $request->all();

        $callback = function () use ($filters) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Adjustment Number',
                'Product',
                'SKU',
                'Type',
                'Quantity',
                'Net Change',
                'Reason',
                'Location',
                'Status',
                'Created By',
                'Approved By',
                'Created At',
                'Approved At',
            ]);

            $query = StockAdjustment::query()->with(['product', 'location']);
            $request = new Request($filters);
            $this->applyFilters($query, $request);
            $query->orderByDesc('created_at');

            $query->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $adjustment) {
                    fputcsv($handle, [
                        $adjustment->adjustment_number,
                        $adjustment->product_name ?? $adjustment->product?->name,
                        $adjustment->product_sku ?? $adjustment->product?->sku,
                        $adjustment->adjustment_type,
                        $adjustment->quantity,
                        $adjustment->quantity_change,
                        $adjustment->reason,
                        $adjustment->location?->name,
                        $adjustment->status,
                        $adjustment->created_by,
                        $adjustment->approved_by,
                        optional($adjustment->created_at)->toDateTimeString(),
                        optional($adjustment->approved_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $query = StockAdjustment::query();
        $this->applyFilters($query, $request, includeSearch: false);

        $totalAdjustments = (clone $query)->count();
        $pending = (clone $query)->where('status', StockAdjustment::STATUS_PENDING)->count();
        $approved = (clone $query)->where('status', StockAdjustment::STATUS_APPROVED)->count();
        $rejected = (clone $query)->where('status', StockAdjustment::STATUS_REJECTED)->count();
        $netQuantityChange = (int) (clone $query)->sum('quantity_change');
        $totalValueAdjusted = (float) (clone $query)->sum('value_change');
        $locationsImpacted = (clone $query)
            ->whereNotNull('location_id')
            ->distinct('location_id')
            ->count('location_id');

        $statusBreakdown = (clone $query)
            ->select(
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_value) as total_value')
            )
            ->groupBy('status')
            ->get()
            ->map(function ($row) use ($totalAdjustments) {
                $count = (int) $row->count;

                return [
                    'status' => $row->status,
                    'count' => $count,
                    'percentage' => $totalAdjustments ? round(($count / $totalAdjustments) * 100, 2) : 0,
                    'totalQuantity' => (int) $row->total_quantity,
                    'totalValue' => (float) $row->total_value,
                ];
            })
            ->values();

        $typeBreakdown = (clone $query)
            ->select(
                'adjustment_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_value) as total_value')
            )
            ->groupBy('adjustment_type')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->adjustment_type,
                'count' => (int) $row->count,
                'totalQuantity' => (int) $row->total_quantity,
                'totalValue' => (float) $row->total_value,
            ])
            ->values();

        $topReasons = (clone $query)
            ->select(
                'reason',
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull('reason')
            ->where('reason', '!=', '')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($totalAdjustments) {
                $count = (int) $row->count;

                return [
                    'reason' => $row->reason,
                    'count' => $count,
                    'percentage' => $totalAdjustments ? round(($count / $totalAdjustments) * 100, 2) : 0,
                ];
            })
            ->values();

        $trend = $this->buildTrendData(clone $query);

        return response()->json([
            'totalAdjustments' => $totalAdjustments,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'netQuantityChange' => $netQuantityChange,
            'totalValueAdjusted' => $totalValueAdjusted,
            'locationsImpacted' => $locationsImpacted,
            'statusBreakdown' => $statusBreakdown,
            'typeBreakdown' => $typeBreakdown,
            'topReasons' => $topReasons,
            'trend' => $trend,
        ]);
    }

    public function generateNumber(): JsonResponse
    {
        return response()->json([
            'adjustmentNumber' => StockAdjustment::generateNumber(),
        ]);
    }

    private function createAdjustment(array $data, string $createdBy): StockAdjustment
    {
        $product = Product::query()->findOrFail($data['productId']);

        $type = strtolower((string) Arr::get($data, 'adjustmentType'));
        $quantity = max(0, (int) Arr::get($data, 'quantity', 0));
        $change = $this->resolveQuantityChange($type, $quantity);
        $currentStock = $product->stock_quantity ?? 0;

        if ($change < 0 && abs($change) > $currentStock) {
            $change = -$currentStock;
        }

        $appliedQuantity = abs($change);
        if ($appliedQuantity < 1 && in_array($type, ['decrease', 'damage', 'loss'], true)) {
            abort(422, 'Insufficient stock available to apply this adjustment.');
        }

        $unitCost = (float) ($product->cost_price ?? 0);
        $newStock = $currentStock + $change;
        if ($newStock < 0) {
            $newStock = 0;
        }

        $locationId = Arr::get($data, 'locationId');
        if ($locationId === '' || $locationId === null) {
            $locationId = null;
        }

        $meta = Arr::get($data, 'meta');
        if (! is_array($meta)) {
            $meta = null;
        }

        $adjustment = new StockAdjustment();
        $adjustment->fill([
            'adjustment_number' => Arr::get($data, 'adjustmentNumber') ?: StockAdjustment::generateNumber(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'location_id' => $locationId,
            'adjustment_type' => $type,
            'quantity' => $appliedQuantity,
            'quantity_change' => $change,
            'previous_stock' => $currentStock,
            'new_stock' => $newStock,
            'reason' => Arr::get($data, 'reason'),
            'notes' => Arr::get($data, 'notes'),
            'unit_cost' => $unitCost,
            'total_value' => $appliedQuantity * $unitCost,
            'value_change' => $change * $unitCost,
            'status' => StockAdjustment::STATUS_PENDING,
            'created_by' => $createdBy,
            'meta' => $meta,
        ]);
        $adjustment->save();

        return $adjustment->load(['product', 'location']);
    }

    private function applyFilters(Builder $query, Request $request, bool $includeSearch = true): void
    {
        if ($includeSearch && ($search = trim((string) $request->input('search', '')))) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('product_sku', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $relation) use ($search) {
                        $relation->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $status = $request->input('status');
        if ($status) {
            $statuses = is_array($status) ? $status : array_filter(array_map('trim', explode(',', (string) $status)));
            if (! empty($statuses)) {
                $query->whereIn('status', array_map('strtolower', $statuses));
            }
        }

        $type = $request->input('type', $request->input('adjustmentType'));
        if ($type) {
            $types = is_array($type) ? $type : array_filter(array_map('trim', explode(',', (string) $type)));
            if (! empty($types)) {
                $query->whereIn('adjustment_type', array_map('strtolower', $types));
            }
        }

        $locationId = $request->input('locationId', $request->input('location_id'));
        if ($locationId) {
            $locations = is_array($locationId)
                ? $locationId
                : array_filter(array_map('trim', explode(',', (string) $locationId)));

            if (! empty($locations)) {
                $query->whereIn('location_id', $locations);
            }
        }

        if ($dateFrom = $this->toDate($request->input('dateFrom', $request->input('date_from')))) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $this->toDate($request->input('dateTo', $request->input('date_to')))) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }

    private function resolveQuantityChange(string $type, int $quantity): int
    {
        $negativeTypes = ['decrease', 'damage', 'loss'];

        return in_array($type, $negativeTypes, true) ? -$quantity : $quantity;
    }

    private function buildTrendData(Builder $query): array
    {
        $now = now();
        $start = $now->copy()->subMonths(5)->startOfMonth();

        $labels = [];
        $period = new \DatePeriod($start, new \DateInterval('P1M'), $now->copy()->endOfMonth()->addMonth());
        foreach ($period as $date) {
            $key = $date->format('Y-m');
            $labels[$key] = [
                'label' => $date->format('M Y'),
                'adjustments' => 0,
                'netQuantity' => 0,
                'totalValue' => 0.0,
            ];
        }

        $rows = (clone $query)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
                DB::raw('COUNT(*) as adjustments'),
                DB::raw('SUM(quantity_change) as net_quantity'),
                DB::raw('SUM(value_change) as total_value')
            )
            ->whereDate('created_at', '>=', $start->toDateString())
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        foreach ($rows as $row) {
            $key = $row->period;
            if (! isset($labels[$key])) {
                $labels[$key] = [
                    'label' => $key,
                    'adjustments' => 0,
                    'netQuantity' => 0,
                    'totalValue' => 0.0,
                ];
            }

            $labels[$key]['adjustments'] = (int) $row->adjustments;
            $labels[$key]['netQuantity'] = (int) $row->net_quantity;
            $labels[$key]['totalValue'] = (float) $row->total_value;
        }

        return array_values($labels);
    }

    private function toDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
