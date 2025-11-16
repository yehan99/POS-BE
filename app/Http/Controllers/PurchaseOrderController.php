<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 10;

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', self::DEFAULT_PAGE_SIZE);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : self::DEFAULT_PAGE_SIZE;

        $query = PurchaseOrder::query()->with(['supplier', 'items']);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($relation) use ($search) {
                        $relation->where('name', 'like', "%{$search}%")
                            ->orWhere('supplier_code', 'like', "%{$search}%");
                    });
            });
        }

        $supplierId = $request->input('supplierId', $request->input('supplier_id'));
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($status = $request->input('status')) {
            $statuses = array_filter(array_map('trim', explode(',', (string) $status)));
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($paymentStatus = $request->input('paymentStatus', $request->input('payment_status'))) {
            $statuses = array_filter(array_map('trim', explode(',', (string) $paymentStatus)));
            if (! empty($statuses)) {
                $query->whereIn('payment_status', $statuses);
            }
        }

        if ($dateFrom = $this->toDate($request->input('dateFrom', $request->input('date_from')))) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $this->toDate($request->input('dateTo', $request->input('date_to')))) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sortable = [
            'poNumber' => 'po_number',
            'total' => 'total',
            'createdAt' => 'created_at',
            'expectedDeliveryDate' => 'expected_delivery_date',
            'status' => 'status',
        ];

        $sortBy = (string) $request->input('sortBy', 'createdAt');
        $sortOrder = strtolower((string) $request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        $column = $sortable[$sortBy] ?? 'created_at';
        $query->orderBy($column, $sortOrder);

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $collection = PurchaseOrderResource::collection($paginator->getCollection());

        return response()->json([
            'data' => $collection->toArray($request),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'totalPages' => $paginator->lastPage(),
        ]);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): PurchaseOrderResource
    {
        $data = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($data, $request) {
            $items = collect($data['items'])->map(function (array $item) {
                $quantity = (int) Arr::get($item, 'quantity', 0);
                $unitCost = (float) Arr::get($item, 'unitCost', 0);
                $tax = (float) Arr::get($item, 'tax', 0);
                $discount = (float) Arr::get($item, 'discount', 0);
                $lineSubtotal = $quantity * $unitCost;
                $lineTotal = $lineSubtotal + $tax - $discount;

                return [
                    'product_id' => Arr::get($item, 'productId'),
                    'product_name' => Arr::get($item, 'productName'),
                    'product_sku' => Arr::get($item, 'productSku'),
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                    'unit_cost' => $unitCost,
                    'tax' => $tax,
                    'discount' => $discount,
                    'total' => max($lineTotal, 0),
                ];
            });

            $totals = $this->calculateTotals($items->all(), (float) Arr::get($data, 'discount', 0), (float) Arr::get($data, 'shippingCost', 0));

            $purchaseOrder = new PurchaseOrder();
            $purchaseOrder->fill([
                'po_number' => PurchaseOrder::generateNumber(),
                'supplier_id' => Arr::get($data, 'supplierId'),
                'status' => Arr::get($data, 'status', 'pending'),
                'payment_status' => Arr::get($data, 'paymentStatus', 'unpaid'),
                'payment_method' => Arr::get($data, 'paymentMethod'),
                'expected_delivery_date' => $this->toDate(Arr::get($data, 'expectedDeliveryDate')),
                'actual_delivery_date' => $this->toDate(Arr::get($data, 'actualDeliveryDate')),
                'discount' => (float) Arr::get($data, 'discount', 0),
                'shipping_cost' => (float) Arr::get($data, 'shippingCost', 0),
                'notes' => Arr::get($data, 'notes'),
                'terms_and_conditions' => Arr::get($data, 'termsAndConditions'),
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
            ]);

            $user = $request->user();
            $purchaseOrder->created_by = $user?->name ?? 'System';

            if ($purchaseOrder->status === 'approved') {
                $purchaseOrder->approved_by = $user?->name ?? 'System';
                $purchaseOrder->approved_at = now();
            }

            $purchaseOrder->save();
            $purchaseOrder->items()->createMany($items->all());

            return $purchaseOrder->load(['supplier', 'items']);
        });

        return PurchaseOrderResource::make($purchaseOrder);
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return PurchaseOrderResource::make($purchaseOrder->load(['supplier', 'items']));
    }

    /**
     * Update the specified purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $data = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $data, $request) {
            $attributes = [];

            if (Arr::has($data, 'supplierId')) {
                $attributes['supplier_id'] = Arr::get($data, 'supplierId');
            }

            foreach ([
                'status' => 'status',
                'paymentStatus' => 'payment_status',
                'paymentMethod' => 'payment_method',
                'discount' => 'discount',
                'shippingCost' => 'shipping_cost',
                'notes' => 'notes',
                'termsAndConditions' => 'terms_and_conditions',
            ] as $input => $column) {
                if (Arr::has($data, $input)) {
                    $attributes[$column] = Arr::get($data, $input);
                }
            }

            if (Arr::has($data, 'expectedDeliveryDate')) {
                $attributes['expected_delivery_date'] = $this->toDate(Arr::get($data, 'expectedDeliveryDate'));
            }

            if (Arr::has($data, 'actualDeliveryDate')) {
                $attributes['actual_delivery_date'] = $this->toDate(Arr::get($data, 'actualDeliveryDate'));
            }

            if (! empty($attributes)) {
                $purchaseOrder->fill($attributes);
            }

            if (Arr::has($data, 'items')) {
                $items = collect($data['items'])->map(function (array $item) {
                    $quantity = (int) Arr::get($item, 'quantity', 0);
                    $unitCost = (float) Arr::get($item, 'unitCost', 0);
                    $tax = (float) Arr::get($item, 'tax', 0);
                    $discount = (float) Arr::get($item, 'discount', 0);
                    $lineSubtotal = $quantity * $unitCost;
                    $lineTotal = $lineSubtotal + $tax - $discount;

                    return [
                        'id' => Arr::get($item, 'id'),
                        'product_id' => Arr::get($item, 'productId'),
                        'product_name' => Arr::get($item, 'productName'),
                        'product_sku' => Arr::get($item, 'productSku'),
                        'quantity' => $quantity,
                        'received_quantity' => (int) Arr::get($item, 'receivedQuantity', 0),
                        'unit_cost' => $unitCost,
                        'tax' => $tax,
                        'discount' => $discount,
                        'total' => max($lineTotal, 0),
                    ];
                });

                $totals = $this->calculateTotals($items->all(), (float) Arr::get($data, 'discount', $purchaseOrder->discount), (float) Arr::get($data, 'shippingCost', $purchaseOrder->shipping_cost));
                $purchaseOrder->subtotal = $totals['subtotal'];
                $purchaseOrder->tax = $totals['tax'];
                $purchaseOrder->total = $totals['total'];

                $existingIds = $purchaseOrder->items()->pluck('id')->all();
                $incomingIds = $items->pluck('id')->filter()->all();
                $idsToDelete = array_diff($existingIds, $incomingIds);
                if (! empty($idsToDelete)) {
                    $purchaseOrder->items()->whereIn('id', $idsToDelete)->delete();
                }

                foreach ($items as $itemData) {
                    $itemId = Arr::get($itemData, 'id');
                    if ($itemId) {
                        $purchaseOrder->items()->updateOrCreate(['id' => $itemId], Arr::except($itemData, ['id']));
                    } else {
                        $purchaseOrder->items()->create(Arr::except($itemData, ['id']));
                    }
                }
            }

            if ($purchaseOrder->isDirty()) {
                $purchaseOrder->save();
            }

            return $purchaseOrder->load(['supplier', 'items']);
        });

        return PurchaseOrderResource::make($purchaseOrder);
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->delete();

        return response()->json(null, 204);
    }

    /**
     * Approve a purchase order.
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        if (! in_array($purchaseOrder->status, ['draft', 'pending'], true)) {
            abort(422, 'Only draft or pending purchase orders can be approved.');
        }

        $user = $request->user();
        $purchaseOrder->status = 'approved';
        $purchaseOrder->approved_at = now();
        $purchaseOrder->approved_by = $user?->name ?? 'System';
        $purchaseOrder->save();

        return PurchaseOrderResource::make($purchaseOrder->load(['supplier', 'items']));
    }

    /**
     * Mark a purchase order as ordered.
     */
    public function ordered(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        if (! in_array($purchaseOrder->status, ['approved', 'pending'], true)) {
            abort(422, 'Only approved purchase orders can be marked as ordered.');
        }

        $purchaseOrder->status = 'ordered';
        $purchaseOrder->ordered_at = now();
        $purchaseOrder->save();

        return PurchaseOrderResource::make($purchaseOrder->load(['supplier', 'items']));
    }

    /**
     * Receive items for a purchase order.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.receivedQuantity' => ['required', 'integer', 'min:0'],
        ]);

        $receivedItems = collect($data['items'])->keyBy('id');

        $allReceived = true;

        DB::transaction(function () use ($purchaseOrder, $receivedItems, &$allReceived) {
            /** @var EloquentCollection<int, PurchaseOrderItem> $items */
            $items = $purchaseOrder->items;
            foreach ($items as $item) {
                $incoming = $receivedItems->get($item->id);
                if (! $incoming) {
                    $allReceived = false;
                    continue;
                }

                $receivedQty = (int) Arr::get($incoming, 'receivedQuantity', 0);
                $item->received_quantity = $receivedQty;
                $item->save();

                if ($receivedQty < $item->quantity) {
                    $allReceived = false;
                }
            }

            if ($allReceived) {
                $purchaseOrder->status = 'received';
                $purchaseOrder->received_at = now();
            } else {
                $purchaseOrder->status = 'partially_received';
                $purchaseOrder->received_at = null;
            }

            $purchaseOrder->received_by = $purchaseOrder->received_by ?? ($purchaseOrder->approved_by ?? 'System');
            $purchaseOrder->save();
        });

        return PurchaseOrderResource::make($purchaseOrder->fresh(['supplier', 'items']));
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        if (in_array($purchaseOrder->status, ['received', 'cancelled'], true)) {
            abort(422, 'Received or already cancelled purchase orders cannot be cancelled.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $purchaseOrder->status = 'cancelled';
        $purchaseOrder->cancelled_at = now();
        $purchaseOrder->cancel_reason = $data['reason'] ?? null;
        $purchaseOrder->save();

        return PurchaseOrderResource::make($purchaseOrder->load(['supplier', 'items']));
    }

    /**
     * Send purchase order to supplier (mock implementation).
     */
    public function send(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $meta = $purchaseOrder->meta ?? [];
        $meta['sentEmails'][] = [
            'email' => $data['email'],
            'sentAt' => now()->toIso8601String(),
        ];

        $purchaseOrder->meta = $meta;
        $purchaseOrder->save();

        Log::info('Purchase order sent (simulated).', [
            'purchase_order_id' => $purchaseOrder->id,
            'email' => $data['email'],
        ]);

        return response()->json([
            'message' => 'Purchase order email queued successfully.',
        ]);
    }

    /**
     * Generate a new purchase order number.
     */
    public function generateNumber(): JsonResponse
    {
        return response()->json([
            'poNumber' => PurchaseOrder::generateNumber(),
        ]);
    }

    /**
     * Purchase order dashboard metrics.
     */
    public function dashboard(): JsonResponse
    {
        $now = now();

        $totalPurchaseOrders = PurchaseOrder::count();
        $pendingApproval = PurchaseOrder::whereIn('status', ['pending', 'draft'])->count();
        $approved = PurchaseOrder::where('status', 'approved')->count();
        $ordered = PurchaseOrder::where('status', 'ordered')->count();
        $partiallyReceived = PurchaseOrder::where('status', 'partially_received')->count();
        $received = PurchaseOrder::where('status', 'received')->count();
        $cancelled = PurchaseOrder::where('status', 'cancelled')->count();
        $overdue = PurchaseOrder::whereNotIn('status', ['cancelled', 'received'])
            ->whereDate('expected_delivery_date', '<', $now->toDateString())
            ->count();

        $totalValue = (float) PurchaseOrder::sum('total');
        $outstandingValue = (float) PurchaseOrder::whereNotIn('status', ['received', 'cancelled'])->sum('total');

        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $spendThisMonth = (float) PurchaseOrder::whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('total');
        $spendLastMonth = (float) PurchaseOrder::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('total');

        $cycleTimes = PurchaseOrder::whereNotNull('received_at')
            ->get()
            ->map(fn (PurchaseOrder $order) => $order->leadTimeDays())
            ->filter()
            ->values();

        $averageCycleTimeDays = $cycleTimes->isNotEmpty()
            ? round($cycleTimes->avg(), 2)
            : 0;

        $statusBreakdown = PurchaseOrder::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_value'))
            ->groupBy('status')
            ->get()
            ->map(function ($row) use ($totalPurchaseOrders) {
                $count = (int) $row->count;
                $totalValue = (float) $row->total_value;
                $percentage = $totalPurchaseOrders > 0 ? round(($count / $totalPurchaseOrders) * 100, 2) : 0;

                return [
                    'status' => $row->status,
                    'count' => $count,
                    'percentage' => $percentage,
                    'totalValue' => $totalValue,
                ];
            })
            ->values();

        $trend = $this->buildTrendData($now);
        $topSuppliers = $this->buildTopSuppliers();

        return response()->json([
            'totalPurchaseOrders' => $totalPurchaseOrders,
            'pendingApproval' => $pendingApproval,
            'inProgress' => $approved + $ordered,
            'partiallyReceived' => $partiallyReceived,
            'received' => $received,
            'cancelled' => $cancelled,
            'overdue' => $overdue,
            'totalValue' => $totalValue,
            'outstandingValue' => $outstandingValue,
            'spendThisMonth' => $spendThisMonth,
            'spendLastMonth' => $spendLastMonth,
            'averageCycleTimeDays' => $averageCycleTimeDays,
            'onTimeFulfillmentRate' => $this->calculateOnTimeFulfillmentRate(),
            'statusBreakdown' => $statusBreakdown,
            'trend' => $trend,
            'topSuppliers' => $topSuppliers,
        ]);
    }

    /**
     * Simplified statistics endpoint.
     */
    public function statistics(): JsonResponse
    {
        $totalPOs = PurchaseOrder::count();
        $pendingApproval = PurchaseOrder::whereIn('status', ['pending', 'draft'])->count();
        $ordered = PurchaseOrder::where('status', 'ordered')->count();
        $partiallyReceived = PurchaseOrder::where('status', 'partially_received')->count();
        $received = PurchaseOrder::where('status', 'received')->count();
        $cancelled = PurchaseOrder::where('status', 'cancelled')->count();
        $overdue = PurchaseOrder::whereNotIn('status', ['cancelled', 'received'])
            ->whereDate('expected_delivery_date', '<', now()->toDateString())
            ->count();
        $totalValue = (float) PurchaseOrder::sum('total');
        $avgDeliveryTime = $this->averageDeliveryTime();

        return response()->json([
            'totalPOs' => $totalPOs,
            'pendingApproval' => $pendingApproval,
            'ordered' => $ordered,
            'partiallyReceived' => $partiallyReceived,
            'received' => $received,
            'cancelled' => $cancelled,
            'overdue' => $overdue,
            'totalValue' => $totalValue,
            'avgDeliveryTime' => $avgDeliveryTime,
        ]);
    }

    /**
     * Export purchase orders to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $fileName = 'purchase_orders_' . now()->format('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($request) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'PO Number',
                'Supplier',
                'Status',
                'Payment Status',
                'Total',
                'Expected Delivery',
                'Created At',
            ]);

            $query = PurchaseOrder::query()->with('supplier');

            if ($status = $request->input('status')) {
                $statuses = array_filter(array_map('trim', explode(',', (string) $status)));
                if (! empty($statuses)) {
                    $query->whereIn('status', $statuses);
                }
            }

            if ($dateFrom = $this->toDate($request->input('dateFrom', $request->input('date_from')))) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo = $this->toDate($request->input('dateTo', $request->input('date_to')))) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $query->orderBy('created_at', 'desc')->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $order) {
                    fputcsv($handle, [
                        $order->po_number,
                        optional($order->supplier)->name,
                        $order->status,
                        $order->payment_status,
                        number_format((float) $order->total, 2, '.', ''),
                        optional($order->expected_delivery_date)?->format('Y-m-d'),
                        optional($order->created_at)?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate a lightweight PDF for a purchase order (placeholder implementation).
     */
    public function pdf(PurchaseOrder $purchaseOrder): StreamedResponse
    {
        $order = $purchaseOrder->load(['supplier', 'items']);
        $content = $this->renderPdfString($order);
        $fileName = 'PO_' . $order->po_number . '.pdf';

        return response()->streamDownload(static function () use ($content) {
            echo $content;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Return empty GRN list placeholder.
     */
    public function grns(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'data' => [],
        ]);
    }

    /**
     * Build dashboard trend collection.
     */
    private function buildTrendData(SupportCarbon $now): array
    {
        $start = $now->copy()->subMonths(5)->startOfMonth();
        $period = CarbonPeriod::create($start, '1 month', $now->copy()->endOfMonth());
        $labels = [];
        foreach ($period as $date) {
            $labels[$date->format('Y-m')] = [
                'label' => $date->format('M Y'),
                'totalValue' => 0,
                'purchaseOrders' => 0,
            ];
        }

        $rows = PurchaseOrder::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
            DB::raw('COUNT(*) as orders'),
            DB::raw('SUM(total) as total_value')
        )
            ->whereDate('created_at', '>=', $start->toDateString())
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        foreach ($rows as $row) {
            $key = $row->period;
            if (! isset($labels[$key])) {
                $labels[$key] = [
                    'label' => Str::replace('-', ' ', $key),
                    'totalValue' => 0,
                    'purchaseOrders' => 0,
                ];
            }

            $labels[$key]['totalValue'] = (float) $row->total_value;
            $labels[$key]['purchaseOrders'] = (int) $row->orders;
        }

        return array_values($labels);
    }

    /**
     * Build top supplier snapshot.
     */
    private function buildTopSuppliers(): array
    {
        $rows = PurchaseOrder::query()
            ->select(
                'supplier_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total) as total_value'),
                DB::raw('AVG(DATEDIFF(COALESCE(actual_delivery_date, NOW()), created_at)) as avg_lead_time'),
                DB::raw('SUM(CASE WHEN actual_delivery_date IS NOT NULL AND expected_delivery_date IS NOT NULL AND actual_delivery_date <= expected_delivery_date THEN 1 ELSE 0 END) as on_time')
            )
            ->groupBy('supplier_id')
            ->orderByDesc('total_value')
            ->limit(6)
            ->get();

        $supplierMap = Supplier::whereIn('id', $rows->pluck('supplier_id')->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($supplierMap) {
            $supplier = $supplierMap->get($row->supplier_id);
            $totalOrders = (int) $row->total_orders;
            $onTime = (int) ($row->on_time ?? 0);
            $onTimeRate = $totalOrders > 0 ? round(($onTime / $totalOrders) * 100, 2) : 0;
            $avgLeadTime = $row->avg_lead_time !== null ? round((float) $row->avg_lead_time, 2) : null;

            return [
                'supplierId' => $row->supplier_id,
                'supplierName' => $supplier?->name ?? 'Supplier',
                'totalOrders' => $totalOrders,
                'totalValue' => (float) $row->total_value,
                'onTimeRate' => $onTimeRate,
                'averageLeadTimeDays' => $avgLeadTime,
            ];
        })->values()->all();
    }

    /**
     * Calculate on-time fulfillment rate.
     */
    private function calculateOnTimeFulfillmentRate(): float
    {
        $orders = PurchaseOrder::whereNotNull('expected_delivery_date')
            ->whereNotNull('actual_delivery_date')
            ->get();

        if ($orders->isEmpty()) {
            return 0;
        }

        $onTime = $orders->filter(function (PurchaseOrder $order) {
            return $order->actual_delivery_date->lessThanOrEqualTo($order->expected_delivery_date);
        })->count();

        return round(($onTime / $orders->count()) * 100, 2);
    }

    /**
     * Average delivery time helper.
     */
    private function averageDeliveryTime(): float
    {
        $orders = PurchaseOrder::whereNotNull('received_at')
            ->whereNotNull('ordered_at')
            ->get();

        if ($orders->isEmpty()) {
            return 0;
        }

        $totalDays = $orders->sum(function (PurchaseOrder $order) {
            return $order->ordered_at->diffInDays($order->received_at);
        });

        return round($totalDays / $orders->count(), 2);
    }

    /**
     * Convert request date value to Carbon instance.
     */
    private function toDate($value): ?SupportCarbon
    {
        if (! $value) {
            return null;
        }

        try {
            return SupportCarbon::parse($value);
        } catch (\Throwable $throwable) {
            return null;
        }
    }

    /**
     * Aggregate totals for purchase order.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function calculateTotals(array $items, float $orderDiscount = 0, float $shippingCost = 0): array
    {
        $subtotal = 0;
        $tax = 0;
        $discount = 0;

        foreach ($items as $item) {
            $lineSubtotal = (int) Arr::get($item, 'quantity', 0) * (float) Arr::get($item, 'unit_cost', Arr::get($item, 'unitCost', 0));
            $lineTax = (float) Arr::get($item, 'tax', 0);
            $lineDiscount = (float) Arr::get($item, 'discount', 0);

            $subtotal += $lineSubtotal;
            $tax += $lineTax;
            $discount += $lineDiscount;
        }

        $discount += $orderDiscount;

        $total = max($subtotal + $tax - $discount + $shippingCost, 0);

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Render a minimal PDF string with basic PO details.
     */
    private function renderPdfString(PurchaseOrder $purchaseOrder): string
    {
        $lines = [
            'Purchase Order',
            'PO Number: ' . $purchaseOrder->po_number,
            'Supplier: ' . optional($purchaseOrder->supplier)->name,
            'Status: ' . $purchaseOrder->status,
            'Total: LKR ' . number_format((float) $purchaseOrder->total, 2),
            'Generated At: ' . now()->toDateTimeString(),
        ];

        $text = implode("\n", $lines);
        $textLength = strlen($text);

        return "%PDF-1.4\n" .
            "1 0 obj<<>>endobj\n" .
            "2 0 obj<< /Length {$textLength} >>stream\n" .
            $text .
            "\nendstream\nendobj\n" .
            "3 0 obj<</Type/Page/Parent 4 0 R/MediaBox[0 0 612 792]/Contents 2 0 R>>endobj\n" .
            "4 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n" .
            "5 0 obj<</Type/Catalog/Pages 4 0 R>>endobj\n" .
            "xref\n0 6\n0000000000 65535 f \n" .
            "0000000010 00000 n \n" .
            "0000000053 00000 n \n" .
            "0000000" . str_pad((string) (53 + $textLength + 45), 4, '0', STR_PAD_LEFT) . " 00000 n \n" .
            "0000000" . str_pad((string) (53 + $textLength + 45 + 65), 4, '0', STR_PAD_LEFT) . " 00000 n \n" .
            "0000000" . str_pad((string) (53 + $textLength + 45 + 65 + 44), 4, '0', STR_PAD_LEFT) . " 00000 n \n" .
            "trailer<</Size 6/Root 5 0 R>>\nstartxref\n" .
            (53 + $textLength + 45 + 65 + 44 + 44) . "\n%%EOF";
    }
}
