<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockTransfer\CancelStockTransferRequest;
use App\Http\Requests\Inventory\StockTransfer\ReceiveStockTransferRequest;
use App\Http\Requests\Inventory\StockTransfer\StoreStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\InventoryLocation;
use App\Models\StockTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockTransferController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 10;

    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', self::DEFAULT_PAGE_SIZE);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : self::DEFAULT_PAGE_SIZE;

        $query = StockTransfer::query()->with(['items', 'fromLocation', 'toLocation']);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('transfer_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $statuses = array_filter(array_map('trim', explode(',', (string) $status)));
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($locationId = $request->input('locationId')) {
            $query->where(function ($builder) use ($locationId) {
                $builder->where('from_location_id', $locationId)
                    ->orWhere('to_location_id', $locationId);
            });
        }

        if ($dateFrom = $this->toDate($request->input('dateFrom', $request->input('date_from')))) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $this->toDate($request->input('dateTo', $request->input('date_to')))) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $query->orderByDesc('created_at');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $collection = StockTransferResource::collection($paginator->getCollection());

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

    public function store(StoreStockTransferRequest $request): StockTransferResource
    {
        $data = $request->validated();

        $transfer = DB::transaction(function () use ($data, $request) {
            $items = collect($data['items'])->map(function (array $item) {
                $quantity = (int) Arr::get($item, 'quantity', 0);
                $unitCost = (float) Arr::get($item, 'unitCost', Arr::get($item, 'unit_cost', 0));

                if ($unitCost <= 0) {
                    $totalCost = (float) Arr::get($item, 'totalCost', 0);
                    $unitCost = $quantity > 0 ? $totalCost / $quantity : 0;
                }

                $totalCost = $quantity * $unitCost;

                return [
                    'product_id' => Arr::get($item, 'productId'),
                    'product_name' => Arr::get($item, 'productName'),
                    'product_sku' => Arr::get($item, 'productSku'),
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                    'unit_cost' => max($unitCost, 0),
                    'total_cost' => max($totalCost, 0),
                ];
            });

            $totals = $this->calculateTotals($items->all());

            $transfer = new StockTransfer();
            $transfer->transfer_number = StockTransfer::generateNumber();
            $transfer->from_location_id = Arr::get($data, 'fromLocationId');
            $transfer->to_location_id = Arr::get($data, 'toLocationId');
            $transfer->status = StockTransfer::STATUS_PENDING;
            $transfer->total_items = $totals['items'];
            $transfer->total_value = $totals['value'];
            $transfer->requested_by = Arr::get($data, 'requestedBy', $request->user()?->name ?? 'System');
            $transfer->notes = Arr::get($data, 'notes');
            $transfer->save();

            $transfer->items()->createMany($items->all());

            return $transfer->load(['items', 'fromLocation', 'toLocation']);
        });

        return StockTransferResource::make($transfer);
    }

    public function show(StockTransfer $transfer): StockTransferResource
    {
        return StockTransferResource::make($transfer->load(['items', 'fromLocation', 'toLocation']));
    }

    public function approve(StockTransfer $transfer, Request $request): StockTransferResource
    {
        if (! in_array($transfer->status, [StockTransfer::STATUS_PENDING, StockTransfer::STATUS_DRAFT], true)) {
            abort(422, 'Only pending transfers can be approved.');
        }

        $transfer->status = StockTransfer::STATUS_APPROVED;
        $transfer->approved_at = now();
        $transfer->approved_by = $request->user()?->name ?? 'System';
        $transfer->save();

        return StockTransferResource::make($transfer->fresh(['items', 'fromLocation', 'toLocation']));
    }

    public function ship(StockTransfer $transfer, Request $request): StockTransferResource
    {
        if ($transfer->status !== StockTransfer::STATUS_APPROVED) {
            abort(422, 'Only approved transfers can be marked as in transit.');
        }

        $transfer->status = StockTransfer::STATUS_IN_TRANSIT;
        $transfer->shipped_at = now();
        $transfer->shipped_by = $request->user()?->name ?? 'System';
        $transfer->save();

        return StockTransferResource::make($transfer->fresh(['items', 'fromLocation', 'toLocation']));
    }

    public function receive(ReceiveStockTransferRequest $request, StockTransfer $transfer): StockTransferResource
    {
        if (! in_array($transfer->status, [StockTransfer::STATUS_IN_TRANSIT, StockTransfer::STATUS_APPROVED], true)) {
            abort(422, 'Only in transit transfers can be received.');
        }

        $data = $request->validated();

        DB::transaction(function () use ($transfer, $data, $request) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockTransferItem> $items */
            $items = $transfer->items;
            $receivedMap = collect($data['receivedItems'])
                ->mapWithKeys(function (array $item) {
                    $key = Arr::get($item, 'itemId') ?? Arr::get($item, 'productId');

                    return $key ? [$key => (int) Arr::get($item, 'receivedQuantity', 0)] : [];
                });

            $allReceived = true;

            foreach ($items as $item) {
                $lookupKeys = array_filter([$item->id, $item->product_id]);
                $receivedQty = 0;
                foreach ($lookupKeys as $key) {
                    if ($receivedMap->has($key)) {
                        $receivedQty = (int) $receivedMap->get($key);
                        break;
                    }
                }

                $receivedQty = max(0, min($receivedQty, $item->quantity));
                $item->received_quantity = $receivedQty;
                $item->save();

                if ($receivedQty < $item->quantity) {
                    $allReceived = false;
                }
            }

            if ($allReceived) {
                $transfer->status = StockTransfer::STATUS_COMPLETED;
                $transfer->received_at = now();
                $transfer->received_by = $request->user()?->name ?? 'System';
            } else {
                $transfer->status = StockTransfer::STATUS_IN_TRANSIT;
                $transfer->received_at = null;
            }

            $transfer->save();
        });

        return StockTransferResource::make($transfer->fresh(['items', 'fromLocation', 'toLocation']));
    }

    public function cancel(CancelStockTransferRequest $request, StockTransfer $transfer): StockTransferResource
    {
        if (in_array($transfer->status, [StockTransfer::STATUS_COMPLETED, StockTransfer::STATUS_CANCELLED], true)) {
            abort(422, 'Completed or already cancelled transfers cannot be cancelled.');
        }

        $transfer->status = StockTransfer::STATUS_CANCELLED;
        $transfer->cancelled_at = now();
        $transfer->cancel_reason = $request->validated()['reason'] ?? null;
        $transfer->save();

        return StockTransferResource::make($transfer->fresh(['items', 'fromLocation', 'toLocation']));
    }

    public function generateNumber(): JsonResponse
    {
        return response()->json([
            'transferNumber' => StockTransfer::generateNumber(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $fileName = 'stock_transfers_' . now()->format('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($request) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Transfer Number',
                'From Location',
                'To Location',
                'Status',
                'Total Items',
                'Total Value',
                'Requested By',
                'Approved By',
                'Shipped At',
                'Received At',
                'Created At',
            ]);

            $query = StockTransfer::query()->with(['fromLocation', 'toLocation']);

            if ($status = $request->input('status')) {
                $statuses = array_filter(array_map('trim', explode(',', (string) $status)));
                if (! empty($statuses)) {
                    $query->whereIn('status', $statuses);
                }
            }

            if ($locationId = $request->input('locationId')) {
                $query->where(function ($builder) use ($locationId) {
                    $builder->where('from_location_id', $locationId)
                        ->orWhere('to_location_id', $locationId);
                });
            }

            if ($dateFrom = $this->toDate($request->input('dateFrom'))) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo = $this->toDate($request->input('dateTo'))) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $query->orderByDesc('created_at')->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $transfer) {
                    fputcsv($handle, [
                        $transfer->transfer_number,
                        optional($transfer->fromLocation)->name,
                        optional($transfer->toLocation)->name,
                        $transfer->status,
                        $transfer->total_items,
                        number_format((float) $transfer->total_value, 2, '.', ''),
                        $transfer->requested_by,
                        $transfer->approved_by,
                        optional($transfer->shipped_at)->toDateTimeString(),
                        optional($transfer->received_at)->toDateTimeString(),
                        optional($transfer->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $totalTransfers = StockTransfer::count();
        $pendingApproval = StockTransfer::where('status', StockTransfer::STATUS_PENDING)->count();
        $inTransit = StockTransfer::where('status', StockTransfer::STATUS_IN_TRANSIT)->count();
        $completed = StockTransfer::where('status', StockTransfer::STATUS_COMPLETED)->count();
        $cancelled = StockTransfer::where('status', StockTransfer::STATUS_CANCELLED)->count();
        $totalItems = (int) StockTransfer::sum('total_items');
        $totalValue = (float) StockTransfer::sum('total_value');

        $statusBreakdown = StockTransfer::select(
            'status',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total_value) as total_value'),
            DB::raw('SUM(total_items) as total_items')
        )
            ->groupBy('status')
            ->get()
            ->map(function ($row) use ($totalTransfers) {
                $count = (int) $row->count;
                $totalValue = (float) $row->total_value;
                $totalItems = (int) $row->total_items;

                return [
                    'status' => $row->status,
                    'count' => $count,
                    'percentage' => $totalTransfers ? round(($count / $totalTransfers) * 100, 2) : 0,
                    'totalValue' => $totalValue,
                    'totalItems' => $totalItems,
                ];
            })
            ->values();

        $trend = $this->buildTrendData();
        $topLocations = $this->buildTopLocations();

        return response()->json([
            'totalTransfers' => $totalTransfers,
            'pendingApproval' => $pendingApproval,
            'inTransit' => $inTransit,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'totalItemsMoved' => $totalItems,
            'totalValueMoved' => $totalValue,
            'statusBreakdown' => $statusBreakdown,
            'trend' => $trend,
            'topLocations' => $topLocations,
        ]);
    }

    private function buildTrendData(): array
    {
        $now = now();
        $start = $now->copy()->subMonths(5)->startOfMonth();

        $labels = [];
        $period = new \DatePeriod($start, new \DateInterval('P1M'), $now->copy()->endOfMonth()->addMonth());
        foreach ($period as $date) {
            $key = $date->format('Y-m');
            $labels[$key] = [
                'label' => $date->format('M Y'),
                'transfers' => 0,
                'totalValue' => 0.0,
                'totalItems' => 0,
            ];
        }

        $rows = StockTransfer::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
            DB::raw('COUNT(*) as transfers'),
            DB::raw('SUM(total_value) as total_value'),
            DB::raw('SUM(total_items) as total_items')
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
                    'transfers' => 0,
                    'totalValue' => 0,
                    'totalItems' => 0,
                ];
            }

            $labels[$key]['transfers'] = (int) $row->transfers;
            $labels[$key]['totalValue'] = (float) $row->total_value;
            $labels[$key]['totalItems'] = (int) $row->total_items;
        }

        return array_values($labels);
    }

    private function buildTopLocations(): array
    {
        $rows = StockTransfer::select(
            'from_location_id',
            DB::raw('COUNT(*) as outbound_transfers'),
            DB::raw('SUM(total_value) as outbound_value'),
            DB::raw('SUM(total_items) as outbound_items')
        )
            ->groupBy('from_location_id')
            ->orderByDesc(DB::raw('SUM(total_value)'))
            ->limit(5)
            ->get();

        $locationIds = $rows->pluck('from_location_id')->filter()->unique();
        $locations = InventoryLocation::whereIn('id', $locationIds)->get(['id', 'name', 'code']);

        return $rows->map(function ($row) use ($locations) {
            $location = $locations->firstWhere('id', $row->from_location_id);

            return [
                'locationId' => $row->from_location_id,
                'locationName' => $location?->name ?? 'Location',
                'locationCode' => $location?->code,
                'outboundTransfers' => (int) $row->outbound_transfers,
                'outboundValue' => (float) $row->outbound_value,
                'outboundItems' => (int) $row->outbound_items,
            ];
        })->values()->all();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function calculateTotals(array $items): array
    {
        $totalItems = 0;
        $totalValue = 0.0;

        foreach ($items as $item) {
            $quantity = (int) Arr::get($item, 'quantity', 0);
            $totalCost = (float) Arr::get($item, 'total_cost', 0);
            $unitCost = (float) Arr::get($item, 'unit_cost', 0);

            if ($totalCost <= 0 && $unitCost > 0) {
                $totalCost = $quantity * $unitCost;
            }

            $totalItems += $quantity;
            $totalValue += $totalCost;
        }

        return [
            'items' => $totalItems,
            'value' => round($totalValue, 2),
        ];
    }

    private function toDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            Log::debug('Failed parsing date value for stock transfers.', [
                'value' => $value,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
