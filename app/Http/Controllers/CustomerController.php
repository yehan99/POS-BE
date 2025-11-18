<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', 10);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : 10;

        $query = Customer::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        if ($loyaltyTier = $request->input('loyaltyTier')) {
            $query->where('loyalty_tier', $loyaltyTier);
        }

        if ($request->filled('isActive')) {
            $query->where('is_active', filter_var($request->input('isActive'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('hasEmail')) {
            $hasEmail = filter_var($request->input('hasEmail'), FILTER_VALIDATE_BOOLEAN);
            $query->when($hasEmail, fn ($q) => $q->whereNotNull('email'))
                ->when(!$hasEmail, fn ($q) => $q->whereNull('email'));
        }

        if ($request->filled('minTotalSpent')) {
            $query->where('total_spent', '>=', (float) $request->input('minTotalSpent'));
        }

        if ($request->filled('maxTotalSpent')) {
            $query->where('total_spent', '<=', (float) $request->input('maxTotalSpent'));
        }

        if ($request->filled('registeredFrom')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->input('registeredFrom')));
        }

        if ($request->filled('registeredTo')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->input('registeredTo')));
        }

        $sortable = [
            'createdAt' => 'created_at',
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'customerCode' => 'customer_code',
            'totalSpent' => 'total_spent',
            'loyaltyPoints' => 'loyalty_points',
        ];

        $sortBy = $request->input('sortBy', 'createdAt');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        $column = $sortable[$sortBy] ?? 'created_at';
        $query->orderBy($column, $sortOrder);

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        $customers = collect($paginator->items())
            ->map(fn ($customer) => CustomerResource::make($customer)->toArray(request()))
            ->all();

        return response()->json([
            'customers' => $customers,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'totalPages' => $paginator->lastPage(),
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $data = $this->mapRequestData($request->validated(), true);
        $data['customer_code'] = $data['customer_code'] ?? Customer::generateCode();

        $customer = Customer::create($data);

        return CustomerResource::make($customer);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $data = $this->mapRequestData($request->validated());
        $customer->update($data);

        return CustomerResource::make($customer->refresh());
    }

    /**
     * Soft delete the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk soft delete customers.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid', 'exists:customers,id'],
        ]);

        Customer::whereIn('id', $validated['ids'])->delete();

        return response()->json(['deleted' => count($validated['ids'])]);
    }

    /**
     * Search customers by phone number.
     */
    public function searchByPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10'],
        ]);

        $customers = Customer::where('phone', 'like', "%{$validated['phone']}%")
            ->where('is_active', true)
            ->get()
            ->map(fn ($customer) => CustomerResource::make($customer)->toArray(request()));

        return response()->json($customers);
    }

    /**
     * Generate a new unique customer code.
     */
    public function generateCode(): JsonResponse
    {
        return response()->json(Customer::generateCode());
    }

    /**
     * Basic statistics for the customer dashboard.
     */
    public function statistics(): JsonResponse
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();
        $newCustomersThisMonth = Customer::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $totalLoyaltyPoints = (int) Customer::sum('loyalty_points');
        $averageSpent = (float) Customer::avg('total_spent');

        $tierData = Customer::select('loyalty_tier', DB::raw('count(*) as total'))
            ->groupBy('loyalty_tier')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->loyalty_tier => (int) $row->total]);

        $tierDistribution = [];
        foreach (['bronze', 'silver', 'gold', 'platinum'] as $tier) {
            $count = $tierData[$tier] ?? 0;
            $percentage = $totalCustomers > 0 ? round(($count / $totalCustomers) * 100, 2) : 0;
            $tierDistribution[] = [
                'tier' => $tier,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        return response()->json([
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'newCustomersThisMonth' => $newCustomersThisMonth,
            'totalLoyaltyPoints' => $totalLoyaltyPoints,
            'averageSpent' => $averageSpent,
            'tierDistribution' => $tierDistribution,
        ]);
    }

    /**
     * Map validated request data to database columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapRequestData(array $data, bool $applyDefaults = false): array
    {
        $mapped = [];

        $set = function (string $key, $value) use (&$mapped) {
            $mapped[$key] = $value;
        };

        if ($applyDefaults || array_key_exists('customerCode', $data)) {
            $set('customer_code', $data['customerCode'] ?? null);
        }

        if ($applyDefaults || array_key_exists('firstName', $data)) {
            $set('first_name', $data['firstName'] ?? null);
        }

        if ($applyDefaults || array_key_exists('lastName', $data)) {
            $set('last_name', $data['lastName'] ?? null);
        }

        if (array_key_exists('email', $data)) {
            $set('email', $data['email']);
        } elseif ($applyDefaults) {
            $set('email', $data['email'] ?? null);
        }

        if ($applyDefaults || array_key_exists('phone', $data)) {
            $set('phone', $data['phone'] ?? null);
        }

        if (array_key_exists('dateOfBirth', $data)) {
            $set('date_of_birth', $data['dateOfBirth']);
        } elseif ($applyDefaults) {
            $set('date_of_birth', $data['dateOfBirth'] ?? null);
        }

        if (array_key_exists('gender', $data)) {
            $set('gender', $data['gender']);
        } elseif ($applyDefaults) {
            $set('gender', $data['gender'] ?? null);
        }

        if (array_key_exists('address', $data)) {
            $set('address', $data['address']);
        } elseif ($applyDefaults) {
            $set('address', $data['address'] ?? null);
        }

        if (array_key_exists('loyaltyPoints', $data)) {
            $set('loyalty_points', $data['loyaltyPoints']);
        } elseif ($applyDefaults) {
            $set('loyalty_points', $data['loyaltyPoints'] ?? 0);
        }

        if (array_key_exists('loyaltyTier', $data)) {
            $set('loyalty_tier', $data['loyaltyTier']);
        } elseif ($applyDefaults) {
            $set('loyalty_tier', $data['loyaltyTier'] ?? 'bronze');
        }

        if (array_key_exists('notes', $data)) {
            $set('notes', $data['notes']);
        } elseif ($applyDefaults) {
            $set('notes', $data['notes'] ?? null);
        }

        if (array_key_exists('isActive', $data)) {
            $set('is_active', (bool) $data['isActive']);
        } elseif ($applyDefaults) {
            $set('is_active', $data['isActive'] ?? true);
        }

        return $mapped;
    }
}
