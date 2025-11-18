<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get complete dashboard summary
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');

        return response()->json([
            'kpis' => $this->getKPIs($period),
            'salesTrend' => $this->getSalesTrend($period),
            'categorySales' => $this->getCategorySales($period),
            'paymentMethodSales' => $this->getPaymentMethodSales($period),
            'recentTransactions' => $this->getRecentTransactions(),
            'inventoryAlerts' => $this->getInventoryAlerts(),
            'topProducts' => $this->getTopProducts(),
            'topCustomers' => $this->getTopCustomers(),
        ]);
    }

    /**
     * Get KPI metrics
     */
    public function kpis(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        return response()->json($this->getKPIs($period));
    }

    /**
     * Calculate KPIs
     */
    private function getKPIs(string $period): array
    {
        $now = Carbon::now();

        // Today's data
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $todaySales = (float) SalesTransaction::whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->where('status', 'completed')
            ->sum('total');

        $todayTransactions = SalesTransaction::whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->where('status', 'completed')
            ->count();

        // Yesterday's data for comparison
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();

        $yesterdaySales = (float) SalesTransaction::whereBetween('transaction_date', [$yesterdayStart, $yesterdayEnd])
            ->where('status', 'completed')
            ->sum('total');

        $yesterdayTransactions = SalesTransaction::whereBetween('transaction_date', [$yesterdayStart, $yesterdayEnd])
            ->where('status', 'completed')
            ->count();

        // Calculate changes
        $todaySalesChange = $yesterdaySales > 0 ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 : 0;
        $todayTransactionsChange = $yesterdayTransactions > 0 ? (($todayTransactions - $yesterdayTransactions) / $yesterdayTransactions) * 100 : 0;

        // Average Order Value
        $averageOrderValue = $todayTransactions > 0 ? $todaySales / $todayTransactions : 0;
        $yesterdayAvgOrderValue = $yesterdayTransactions > 0 ? $yesterdaySales / $yesterdayTransactions : 0;
        $averageOrderValueChange = $yesterdayAvgOrderValue > 0 ? (($averageOrderValue - $yesterdayAvgOrderValue) / $yesterdayAvgOrderValue) * 100 : 0;

        // Customer metrics
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();
        $newCustomersToday = Customer::whereDate('created_at', $now->toDateString())->count();

        // Product metrics
        $totalProducts = Product::count();
        $lowStockProducts = Product::where('stock_quantity', '>', 0)
            ->whereRaw('stock_quantity <= reorder_level')
            ->count();
        $outOfStockProducts = Product::where('stock_quantity', 0)->count();
        $stockValue = (float) Product::selectRaw('SUM(stock_quantity * cost_price) as total')
            ->value('total') ?? 0;

        // Week and Month sales
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $weekSales = (float) SalesTransaction::where('transaction_date', '>=', $weekStart)
            ->where('status', 'completed')
            ->sum('total');

        $monthSales = (float) SalesTransaction::where('transaction_date', '>=', $monthStart)
            ->where('status', 'completed')
            ->sum('total');

        return [
            'todaySales' => round($todaySales, 2),
            'todaySalesChange' => round($todaySalesChange, 2),
            'weekSales' => round($weekSales, 2),
            'weekSalesChange' => 0, // TODO: Calculate week over week
            'monthSales' => round($monthSales, 2),
            'monthSalesChange' => 0, // TODO: Calculate month over month
            'todayTransactions' => $todayTransactions,
            'todayTransactionsChange' => round($todayTransactionsChange, 2),
            'averageOrderValue' => round($averageOrderValue, 2),
            'averageOrderValueChange' => round($averageOrderValueChange, 2),
            'totalCustomers' => $totalCustomers,
            'totalCustomersChange' => 0,
            'activeCustomers' => $activeCustomers,
            'newCustomersToday' => $newCustomersToday,
            'totalProducts' => $totalProducts,
            'lowStockProducts' => $lowStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'stockValue' => round($stockValue, 2),
        ];
    }

    /**
     * Get sales trend data
     */
    public function salesTrend(Request $request): JsonResponse
    {
        $period = $request->input('period', 'week');
        return response()->json($this->getSalesTrend($period));
    }

    private function getSalesTrend(string $period): array
    {
        $days = $period === 'week' ? 7 : ($period === 'month' ? 30 : 7);
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        return SalesTransaction::selectRaw('DATE(transaction_date) as date, SUM(total) as sales, COUNT(*) as transactions')
            ->where('transaction_date', '>=', $startDate)
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'sales' => (float) $item->sales,
                    'transactions' => (int) $item->transactions,
                ];
            })
            ->toArray();

        return $trends;
    }

    /**
     * Get sales by category
     */
    public function categorySales(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');
        return response()->json($this->getCategorySales($period));
    }

    private function getCategorySales(string $period): array
    {
        // TODO: Implement based on your transaction items table structure
        // Placeholder return
        return [];
    }

    /**
     * Get payment method sales
     */
    public function paymentMethodSales(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        return response()->json($this->getPaymentMethodSales($period));
    }

    private function getPaymentMethodSales(string $period): array
    {
        $startDate = $this->getStartDateByPeriod($period);

        $payments = SalesTransaction::selectRaw('payment_method, SUM(total) as amount, COUNT(*) as count')
            ->where('transaction_date', '>=', $startDate)
            ->where('status', 'completed')
            ->groupBy('payment_method')
            ->get();

        $total = $payments->sum('amount');

        return $payments->map(function ($payment) use ($total) {
            return [
                'method' => ucfirst($payment->payment_method),
                'amount' => (float) $payment->amount,
                'count' => $payment->count,
                'percentage' => $total > 0 ? round(($payment->amount / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    /**
     * Get recent transactions
     */
    public function recentTransactions(Request $request): JsonResponse
    {
        return response()->json($this->getRecentTransactions($request->input('limit', 10)));
    }

    private function getRecentTransactions(int $limit = 10): array
    {
        $transactions = SalesTransaction::with('customer')
            ->orderBy('transaction_date', 'desc')
            ->limit($limit)
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->transaction_number,
                'transactionDate' => $transaction->transaction_date->toISOString(),
                'customerName' => $transaction->customer ?
                    $transaction->customer->first_name . ' ' . $transaction->customer->last_name :
                    'Walk-in Customer',
                'items' => $transaction->total_items ?? 0,
                'amount' => (float) $transaction->total,
                'paymentMethod' => ucfirst($transaction->payment_method),
                'status' => $transaction->status,
            ];
        })->toArray();
    }

    /**
     * Get inventory alerts
     */
    public function inventoryAlerts(): JsonResponse
    {
        return response()->json($this->getInventoryAlerts());
    }

    private function getInventoryAlerts(): array
    {
        $alerts = Product::where(function ($query) {
            $query->where('stock_quantity', 0)
                ->orWhereRaw('stock_quantity <= reorder_level');
        })
        ->orderBy('stock_quantity')
        ->limit(10)
        ->get();

        return $alerts->map(function ($product) {
            $severity = $product->stock_quantity == 0 ? 'critical' : 'warning';
            $alertType = $product->stock_quantity == 0 ? 'out_of_stock' : 'low_stock';

            return [
                'productId' => $product->id,
                'productName' => $product->name,
                'sku' => $product->sku,
                'currentStock' => $product->stock_quantity,
                'minStock' => $product->reorder_level ?? 10,
                'alertType' => $alertType,
                'severity' => $severity,
            ];
        })->toArray();
    }

    /**
     * Get top selling products
     */
    public function topProducts(Request $request): JsonResponse
    {
        return response()->json($this->getTopProducts($request->input('limit', 5)));
    }

    private function getTopProducts(int $limit = 5): array
    {
        // TODO: Implement based on transaction items
        // Placeholder
        return [];
    }

    /**
     * Get top customers
     */
    public function topCustomers(Request $request): JsonResponse
    {
        return response()->json($this->getTopCustomers($request->input('limit', 5)));
    }

    private function getTopCustomers(int $limit = 5): array
    {
        $customers = Customer::select(
                'customers.id',
                'customers.first_name',
                'customers.last_name',
                'customers.email',
                'customers.phone',
                'customers.loyalty_tier',
                DB::raw('COUNT(sales_transactions.id) as purchase_count'),
                DB::raw('COALESCE(SUM(sales_transactions.total), 0) as total_spent'),
                DB::raw('MAX(sales_transactions.transaction_date) as last_purchase')
            )
            ->leftJoin('sales_transactions', function ($join) {
                $join->on('customers.id', '=', 'sales_transactions.customer_id')
                    ->where('sales_transactions.status', 'completed');
            })
            ->groupBy(
                'customers.id',
                'customers.first_name',
                'customers.last_name',
                'customers.email',
                'customers.phone',
                'customers.loyalty_tier'
            )
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return $customers->map(function ($customer) {
            return [
                'customerId' => $customer->id,
                'customerName' => $customer->first_name . ' ' . $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'totalPurchases' => $customer->purchase_count ?? 0,
                'totalSpent' => (float) ($customer->total_spent ?? 0),
                'lastPurchaseDate' => $customer->last_purchase ?
                    Carbon::parse($customer->last_purchase)->toISOString() : null,
                'tier' => $customer->loyalty_tier ?? 'bronze',
            ];
        })->toArray();
    }

    /**
     * Helper method to get start date by period
     */
    private function getStartDateByPeriod(string $period): Carbon
    {
        $now = Carbon::now();

        return match($period) {
            'today' => $now->startOfDay(),
            'yesterday' => $now->subDay()->startOfDay(),
            'week' => $now->startOfWeek(),
            'month' => $now->startOfMonth(),
            'year' => $now->startOfYear(),
            default => $now->startOfDay(),
        };
    }
}
