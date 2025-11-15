<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerLoyaltyTransactionRequest;
use App\Http\Resources\CustomerLoyaltyTransactionResource;
use App\Models\Customer;
use App\Services\CustomerLoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLoyaltyTransactionController extends Controller
{
    public function __construct(private readonly CustomerLoyaltyService $loyaltyService)
    {
    }

    public function index(Request $request, Customer $customer): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = (int) $request->input('pageSize', 15);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : 15;

        $paginator = $customer->loyaltyTransactions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);

        $transactions = collect($paginator->items())
            ->map(fn ($transaction) => CustomerLoyaltyTransactionResource::make($transaction)->toArray($request))
            ->all();

        return response()->json([
            'transactions' => $transactions,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'totalPages' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreCustomerLoyaltyTransactionRequest $request, Customer $customer): CustomerLoyaltyTransactionResource
    {
        $transaction = $this->loyaltyService->recordTransaction($customer, $request->validated());

        return CustomerLoyaltyTransactionResource::make($transaction);
    }
}
