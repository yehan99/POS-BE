<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', 10);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : 10;

        $query = Supplier::query();

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('supplier_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $sortable = [
            'name' => 'name',
            'status' => 'status',
            'category' => 'category',
            'rating' => 'rating',
            'totalSpent' => 'total_spent',
            'spendThisMonth' => 'spend_this_month',
            'averageLeadTime' => 'average_lead_time_days',
            'onTimeDeliveryRate' => 'on_time_delivery_rate',
            'createdAt' => 'created_at',
        ];

        $sortBy = $request->input('sortBy', 'createdAt');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        $column = $sortable[$sortBy] ?? 'created_at';
        $query->orderBy($column, $sortOrder);

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $suppliers = collect($paginator->items())
            ->map(fn (Supplier $supplier) => SupplierResource::make($supplier)->toArray($request))
            ->all();

        return response()->json([
            'data' => $suppliers,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'totalPages' => $paginator->lastPage(),
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $data = $request->validated();
        $attributes = $this->mapRequestData($data, true);
        $attributes['supplier_code'] = $attributes['supplier_code'] ?? Supplier::generateCode();

        $supplier = Supplier::create($attributes);

        return SupplierResource::make($supplier);
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier): SupplierResource
    {
        return SupplierResource::make($supplier);
    }

    /**
     * Update the specified supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $data = $request->validated();
        $attributes = $this->mapRequestData($data);
        $supplier->update($attributes);

        return SupplierResource::make($supplier->refresh());
    }

    /**
     * Soft delete the specified supplier.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk delete suppliers.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid', 'exists:suppliers,id'],
        ]);

        Supplier::whereIn('id', $validated['ids'])->delete();

        return response()->json(['deleted' => count($validated['ids'])]);
    }

    /**
     * List active suppliers for dropdowns.
     */
    public function active(): JsonResponse
    {
        $suppliers = Supplier::where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'supplierCode' => $supplier->supplier_code,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
            ]);

        return response()->json($suppliers);
    }

    /**
     * Generate a new supplier code.
     */
    public function generateCode(): JsonResponse
    {
        return response()->json(Supplier::generateCode());
    }

    /**
     * Supplier dashboard summary metrics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->input('period', 'this_month');
        [$start, $end] = $this->resolvePeriod($period);

        $totalSuppliers = Supplier::count();
        $activeSuppliers = Supplier::where('status', 'active')->count();
        $newSuppliers = Supplier::when($start && $end, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->count();
        $preferredSuppliers = Supplier::where('is_preferred', true)->count();

        $averageLeadTime = (float) Supplier::avg('average_lead_time_days') ?? 0;
        $onTimeDeliveryRate = (float) Supplier::avg('on_time_delivery_rate') ?? 0;
        $totalSpend = (float) Supplier::sum('total_spent');

        $spendThisPeriod = match ($period) {
            'this_month' => (float) Supplier::sum('spend_this_month'),
            'last_month' => (float) Supplier::sum('spend_last_month'),
            default => (float) Supplier::when($start && $end, fn ($q) => $q->whereBetween('last_purchase_at', [$start, $end]))
                ->sum('total_spent'),
        };

        $previousSpend = match ($period) {
            'this_month' => (float) Supplier::sum('spend_last_month'),
            'last_month' => (float) Supplier::sum('spend_this_month'),
            default => $totalSpend,
        };

        $spendGrowth = $previousSpend > 0
            ? (($spendThisPeriod - $previousSpend) / $previousSpend) * 100
            : ($spendThisPeriod > 0 ? 100 : 0);

        $categoryRows = Supplier::select('category', DB::raw('count(*) as supplier_count'), DB::raw('SUM(total_spent) as total_spent'))
            ->groupBy('category')
            ->orderByDesc(DB::raw('total_spent'))
            ->get();

        $categoryBreakdown = $categoryRows->map(function ($row) use ($totalSuppliers) {
            $count = (int) $row->supplier_count;
            $percentage = $totalSuppliers > 0 ? round(($count / $totalSuppliers) * 100, 2) : 0;

            return [
                'category' => $row->category ?? 'Uncategorised',
                'supplierCount' => $count,
                'percentage' => $percentage,
                'totalSpend' => (float) $row->total_spent,
            ];
        })->values();

        $trend = $this->buildTrendData();

        return response()->json([
            'totalSuppliers' => $totalSuppliers,
            'activeSuppliers' => $activeSuppliers,
            'newSuppliersThisMonth' => $newSuppliers,
            'preferredSuppliers' => $preferredSuppliers,
            'averageLeadTimeDays' => round($averageLeadTime, 2),
            'onTimeDeliveryRate' => round($onTimeDeliveryRate, 2),
            'totalSpend' => $totalSpend,
            'spendThisMonth' => $spendThisPeriod,
            'spendGrowthPercentage' => round($spendGrowth, 2),
            'categoryBreakdown' => $categoryBreakdown,
            'trend' => $trend,
            'topSuppliers' => $this->buildTopSuppliers(),
        ]);
    }

    /**
     * Supplier level statistics for detail view.
     */
    public function statistics(Supplier $supplier): JsonResponse
    {
        $totalOrders = max((int) $supplier->total_orders, (int) $supplier->total_purchases);
        $totalSpent = (float) $supplier->total_spent;
        $averageOrderValue = $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0;

        return response()->json([
            'totalPurchaseOrders' => $totalOrders,
            'totalSpent' => $totalSpent,
            'averageOrderValue' => $averageOrderValue,
            'onTimeDeliveryRate' => (float) $supplier->on_time_delivery_rate,
            'pendingOrders' => 0,
        ]);
    }

    /**
     * Supplier purchase history derived from monthly spend stats.
     */
    public function purchaseHistory(Request $request, Supplier $supplier): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', 10);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : 10;

        $history = collect($supplier->monthly_spend_stats ?? [])
            ->map(function (array $row) use ($supplier) {
                $period = Arr::get($row, 'period');
                $label = $this->formatPeriodLabel($period);

                return [
                    'period' => $label ?? $period ?? 'N/A',
                    'totalSpend' => (float) Arr::get($row, 'totalSpend', 0),
                    'purchaseOrders' => (int) Arr::get($row, 'purchaseOrders', 0),
                    'averageLeadTimeDays' => (float) Arr::get($row, 'averageLeadTimeDays', $supplier->average_lead_time_days ?? 0),
                    '_sortKey' => $period ?? '0000-00',
                ];
            })
            ->sortByDesc(fn ($row) => $row['_sortKey'])
            ->values();

        $total = $history->count();
        $items = $history->slice(($page - 1) * $pageSize, $pageSize)
            ->map(function ($row) {
                unset($row['_sortKey']);
                return $row;
            })
            ->values();

        return response()->json([
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => (int) ceil($total / $pageSize),
        ]);
    }

    /**
     * Export suppliers as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['search', 'status', 'category']);

        $query = Supplier::query();

        if ($search = Arr::get($filters, 'search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('supplier_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = Arr::get($filters, 'status')) {
            $query->where('status', $status);
        }

        if ($category = Arr::get($filters, 'category')) {
            $query->where('category', $category);
        }

        $fileName = 'suppliers_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $columns = [
            'supplier_code', 'name', 'email', 'phone', 'category', 'status',
            'total_orders', 'total_spent', 'rating', 'on_time_delivery_rate', 'average_lead_time_days',
        ];

        $callback = function () use ($query, $columns): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Supplier Code',
                'Name',
                'Email',
                'Phone',
                'Category',
                'Status',
                'Total Orders',
                'Total Spent',
                'Rating',
                'On-Time Delivery Rate',
                'Average Lead Time (days)',
            ]);

            $query->orderBy('name')->chunk(200, function ($suppliers) use ($handle, $columns): void {
                foreach ($suppliers as $supplier) {
                    $row = [];
                    foreach ($columns as $column) {
                        $row[] = $supplier->{$column};
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import suppliers from CSV.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            abort(422, 'Unable to read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            abort(422, 'The CSV file is empty.');
        }

        $header = array_map(fn ($value) => Str::of($value ?? '')->trim()->snake()->toString(), $header);
        $requiredColumns = ['supplier_code', 'name'];

        foreach ($requiredColumns as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);
                abort(422, "Missing required column: {$column}");
            }
        }

        $success = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && trim($row[0]) === '') {
                continue;
            }

            $payload = array_combine($header, $row);
            if ($payload === false) {
                $errors[] = 'Row could not be processed due to mismatched columns.';
                continue;
            }

            $normalized = $this->normalizeImportedRow($payload);
            $validator = Validator::make($normalized, [
                'supplierCode' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:150'],
                'email' => ['nullable', 'email', 'max:150'],
                'phone' => ['nullable', 'string', 'max:60'],
                'status' => ['nullable', 'in:active,inactive,blocked'],
            ]);

            if ($validator->fails()) {
                $errors[] = 'Row validation failed: ' . implode('; ', $validator->errors()->all());
                continue;
            }

            $attributes = $this->mapRequestData($validator->validated(), true);

            $existing = Supplier::withTrashed()
                ->where('supplier_code', $attributes['supplier_code'])
                ->first();

            if ($existing) {
                $existing->restore();
                $existing->fill($attributes);
                $existing->save();
            } else {
                Supplier::create($attributes);
            }

            $success++;
        }

        fclose($handle);

        return response()->json([
            'success' => $success,
            'errors' => $errors,
        ]);
    }

    /**
     * Map validated request data to database fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapRequestData(array $data, bool $applyDefaults = false): array
    {
        $mapped = [];

        $set = function (string $key, $value) use (&$mapped): void {
            $mapped[$key] = $value;
        };

        if ($applyDefaults || Arr::exists($data, 'supplierCode')) {
            $set('supplier_code', Arr::get($data, 'supplierCode'));
        }

        if ($applyDefaults || Arr::exists($data, 'name')) {
            $set('name', Arr::get($data, 'name'));
        }

        if (Arr::exists($data, 'contactPerson')) {
            $set('contact_person', Arr::get($data, 'contactPerson'));
        } elseif ($applyDefaults) {
            $set('contact_person', Arr::get($data, 'contactPerson'));
        }

        if (Arr::exists($data, 'email')) {
            $set('email', Arr::get($data, 'email'));
        } elseif ($applyDefaults) {
            $set('email', Arr::get($data, 'email'));
        }

        if (Arr::exists($data, 'phone')) {
            $set('phone', Arr::get($data, 'phone'));
        } elseif ($applyDefaults) {
            $set('phone', Arr::get($data, 'phone'));
        }

        if (Arr::exists($data, 'category')) {
            $set('category', Arr::get($data, 'category'));
        } elseif ($applyDefaults) {
            $set('category', Arr::get($data, 'category'));
        }

        if (Arr::exists($data, 'status')) {
            $status = Arr::get($data, 'status');
            $set('status', $status);
            if ($status === 'active') {
                $set('is_active', true);
            } elseif ($status === 'inactive') {
                $set('is_active', false);
            }
        } elseif ($applyDefaults) {
            $set('status', Arr::get($data, 'status', 'active'));
        }

        if (Arr::exists($data, 'isActive')) {
            $isActive = (bool) Arr::get($data, 'isActive');
            $set('is_active', $isActive);
            if (! Arr::exists($data, 'status')) {
                $set('status', $isActive ? 'active' : 'inactive');
            }
        } elseif ($applyDefaults) {
            $set('is_active', (bool) Arr::get($data, 'isActive', true));
        }

        if (Arr::exists($data, 'isPreferred')) {
            $set('is_preferred', (bool) Arr::get($data, 'isPreferred'));
        } elseif ($applyDefaults) {
            $set('is_preferred', (bool) Arr::get($data, 'isPreferred', false));
        }

        if (Arr::exists($data, 'paymentTerms')) {
            $set('payment_terms', Arr::get($data, 'paymentTerms'));
        } elseif ($applyDefaults) {
            $set('payment_terms', Arr::get($data, 'paymentTerms'));
        }

        foreach ([
            'creditLimit' => 'credit_limit',
            'rating' => 'rating',
            'totalPurchases' => 'total_purchases',
            'totalOrders' => 'total_orders',
            'totalSpent' => 'total_spent',
            'spendThisMonth' => 'spend_this_month',
            'spendLastMonth' => 'spend_last_month',
            'onTimeDeliveryRate' => 'on_time_delivery_rate',
            'averageLeadTimeDays' => 'average_lead_time_days',
        ] as $input => $column) {
            if (Arr::exists($data, $input)) {
                $set($column, Arr::get($data, $input));
            } elseif ($applyDefaults) {
                $set($column, Arr::get($data, $input));
            }
        }

        if (Arr::exists($data, 'taxId')) {
            $set('tax_id', Arr::get($data, 'taxId'));
        } elseif ($applyDefaults) {
            $set('tax_id', Arr::get($data, 'taxId'));
        }

        if (Arr::exists($data, 'website')) {
            $set('website', Arr::get($data, 'website'));
        } elseif ($applyDefaults) {
            $set('website', Arr::get($data, 'website'));
        }

        if (Arr::exists($data, 'address')) {
            $set('address', Arr::get($data, 'address'));
        } elseif ($applyDefaults) {
            $set('address', Arr::get($data, 'address', []));
        }

        if (Arr::exists($data, 'bankDetails')) {
            $set('bank_details', Arr::get($data, 'bankDetails'));
        } elseif ($applyDefaults) {
            $set('bank_details', Arr::get($data, 'bankDetails', []));
        }

        if (Arr::exists($data, 'notes')) {
            $set('notes', Arr::get($data, 'notes'));
        } elseif ($applyDefaults) {
            $set('notes', Arr::get($data, 'notes'));
        }

        if (Arr::exists($data, 'lastPurchaseDate')) {
            $set('last_purchase_at', Arr::get($data, 'lastPurchaseDate'));
        } elseif ($applyDefaults) {
            $set('last_purchase_at', Arr::get($data, 'lastPurchaseDate'));
        }

        if (Arr::exists($data, 'monthlySpendStats')) {
            $set('monthly_spend_stats', Arr::get($data, 'monthlySpendStats'));
        } elseif ($applyDefaults) {
            $set('monthly_spend_stats', Arr::get($data, 'monthlySpendStats', []));
        }

        return $mapped;
    }

    /**
     * Resolve the date range for a dashboard period string.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::today()->endOfDay()],
            'yesterday' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'this_week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'last_week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            'last_year' => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()],
            default => [null, null],
        };
    }

    /**
     * Build supplier trend data based on monthly spend stats.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildTrendData(): array
    {
        $trend = [];

        Supplier::select(['monthly_spend_stats'])->chunk(200, function ($suppliers) use (&$trend): void {
            foreach ($suppliers as $supplier) {
                foreach ((array) $supplier->monthly_spend_stats as $entry) {
                    $period = Arr::get($entry, 'period');
                    if (! $period) {
                        continue;
                    }

                    if (! isset($trend[$period])) {
                        $trend[$period] = [
                            'period' => $period,
                            'totalSpend' => 0.0,
                            'purchaseOrders' => 0,
                        ];
                    }

                    $trend[$period]['totalSpend'] += (float) Arr::get($entry, 'totalSpend', 0);
                    $trend[$period]['purchaseOrders'] += (int) Arr::get($entry, 'purchaseOrders', 0);
                }
            }
        });

        return collect($trend)
            ->sortKeysDesc()
            ->map(function (array $row) {
                $period = Arr::get($row, 'period');
                $label = $this->formatPeriodLabel($period) ?? $period;

                return [
                    'label' => $label,
                    'totalSpend' => round((float) Arr::get($row, 'totalSpend', 0), 2),
                    'purchaseOrders' => (int) Arr::get($row, 'purchaseOrders', 0),
                ];
            })
            ->values()
            ->take(6)
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * Retrieve the top performing suppliers for the dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildTopSuppliers(): array
    {
        return Supplier::orderByDesc('spend_this_month')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get()
            ->map(function (Supplier $supplier) {
                return [
                    'supplierId' => $supplier->id,
                    'supplierName' => $supplier->name,
                    'rating' => $supplier->rating !== null ? (float) $supplier->rating : null,
                    'totalOrders' => (int) max($supplier->total_orders, $supplier->total_purchases),
                    'totalSpend' => $supplier->spend_this_month !== null
                        ? (float) $supplier->spend_this_month
                        : (float) $supplier->total_spent,
                    'onTimeDeliveryRate' => (float) $supplier->on_time_delivery_rate,
                    'averageLeadTimeDays' => (float) $supplier->average_lead_time_days,
                ];
            })
            ->all();
    }

    /**
     * Normalise CSV row payload into request friendly array.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeImportedRow(array $payload): array
    {
        return [
            'supplierCode' => Arr::get($payload, 'supplier_code'),
            'name' => Arr::get($payload, 'name'),
            'email' => Arr::get($payload, 'email'),
            'phone' => Arr::get($payload, 'phone'),
            'category' => Arr::get($payload, 'category'),
            'status' => Arr::get($payload, 'status'),
            'totalOrders' => Arr::get($payload, 'total_orders'),
            'totalSpent' => Arr::get($payload, 'total_spent'),
            'rating' => Arr::get($payload, 'rating'),
            'onTimeDeliveryRate' => Arr::get($payload, 'on_time_delivery_rate'),
            'averageLeadTimeDays' => Arr::get($payload, 'average_lead_time_days'),
        ];
    }

    /**
     * Safely format a YYYY-MM period label.
     */
    protected function formatPeriodLabel(?string $period): ?string
    {
        if (! $period) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m', $period)->format('M Y');
        } catch (\Throwable $e) {
            return $period;
        }
    }
}
