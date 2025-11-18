<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            [$startDate, $endDate] = $this->resolveDateRange($request);

            $baseQuery = SalesTransaction::query()
                ->when($startDate, fn ($query) => $query->where('transaction_date', '>=', $startDate))
                ->when($endDate, fn ($query) => $query->where('transaction_date', '<=', $endDate));

            $summary = $this->buildSummary(clone $baseQuery);
            $trend = $this->buildTrend(clone $baseQuery, $startDate, $endDate);
            $paymentBreakdown = $this->buildPaymentBreakdown(clone $baseQuery);
            $statusBreakdown = $this->buildStatusBreakdown(clone $baseQuery);
            $topCashiers = $this->buildTopCashiers(clone $baseQuery);
            $recentTransactions = $this->getRecentTransactions(clone $baseQuery);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'trend' => $trend,
                    'paymentBreakdown' => $paymentBreakdown,
                    'statusBreakdown' => $statusBreakdown,
                    'topCashiers' => $topCashiers,
                    'recentTransactions' => $recentTransactions,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load transaction dashboard data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveDateRange(Request $request): array
    {
        if ($request->filled('startDate') && $request->filled('endDate')) {
            return [
                Carbon::parse($request->startDate)->startOfDay(),
                Carbon::parse($request->endDate)->endOfDay(),
            ];
        }

        $period = $request->input('period', 'today');
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [null, null],
        };
    }

    private function buildSummary($query): array
    {
        $totalTransactions = (clone $query)->count();
        $completedQuery = (clone $query)->where('status', 'completed');
        $completedTransactions = (clone $completedQuery)->count();
        $pendingTransactions = (clone $query)->where('status', 'pending')->count();
        $cancelledTransactions = (clone $query)->where('status', 'cancelled')->count();
        $totalSales = (float) (clone $completedQuery)->sum('total');
        $totalRefunds = (float) (clone $query)->where('status', 'refunded')->sum('total');

        return [
            'totalTransactions' => $totalTransactions,
            'completedTransactions' => $completedTransactions,
            'pendingTransactions' => $pendingTransactions,
            'cancelledTransactions' => $cancelledTransactions,
            'totalSales' => round($totalSales, 2),
            'netSales' => round($totalSales - $totalRefunds, 2),
            'totalRefunds' => round($totalRefunds, 2),
            'averageTransactionValue' => $completedTransactions > 0
                ? round($totalSales / $completedTransactions, 2)
                : 0,
            'cashSales' => round((clone $completedQuery)->where('payment_method', 'cash')->sum('total'), 2),
            'cardSales' => round((clone $completedQuery)->where('payment_method', 'card')->sum('total'), 2),
            'mobileSales' => round((clone $completedQuery)->where('payment_method', 'mobile')->sum('total'), 2),
        ];
    }

    private function buildTrend($query, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $maxPoints = 30;

        if ($startDate && $endDate) {
            $days = $startDate->diffInDays($endDate) + 1;
            $maxPoints = min(max($days, 1), 90);
        }

        return (clone $query)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(transaction_date) as date, SUM(total) as total_sales, COUNT(*) as transaction_count')
            ->groupBy('date')
            ->orderBy('date')
            ->limit($maxPoints)
            ->get()
            ->map(function ($row) {
                return [
                    'date' => Carbon::parse($row->date)->toDateString(),
                    'totalSales' => round((float) $row->total_sales, 2),
                    'transactionCount' => (int) $row->transaction_count,
                ];
            })
            ->toArray();
    }

    private function buildPaymentBreakdown($query): array
    {
        $total = (float) (clone $query)
            ->where('status', 'completed')
            ->sum('total');

        return (clone $query)
            ->where('status', 'completed')
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as transaction_count, SUM(total) as amount')
            ->groupBy('payment_method')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) use ($total) {
                $amount = (float) $row->amount;

                return [
                    'method' => $row->payment_method,
                    'transactionCount' => (int) $row->transaction_count,
                    'amount' => round($amount, 2),
                    'percentage' => $total > 0 ? round(($amount / $total) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    private function buildStatusBreakdown($query): array
    {
        $totalTransactions = max((clone $query)->count(), 1);

        return (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(function ($row) use ($totalTransactions) {
                return [
                    'status' => $row->status,
                    'count' => (int) $row->count,
                    'percentage' => round(($row->count / $totalTransactions) * 100, 2),
                ];
            })
            ->toArray();
    }

    private function buildTopCashiers($query): array
    {
        return (clone $query)
            ->where('status', 'completed')
            ->whereNotNull('cashier_id')
            ->select([
                'cashier_id',
                'cashier_name',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total) as total_sales'),
                DB::raw('AVG(total) as average_sale_value'),
            ])
            ->groupBy('cashier_id', 'cashier_name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'cashierId' => (string) $row->cashier_id,
                    'cashierName' => $row->cashier_name,
                    'transactionCount' => (int) $row->transaction_count,
                    'totalSales' => round((float) $row->total_sales, 2),
                    'averageSaleValue' => round((float) $row->average_sale_value, 2),
                ];
            })
            ->toArray();
    }

    private function getRecentTransactions($query): array
    {
        return (clone $query)
            ->with('customer')
            ->orderBy('transaction_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function (SalesTransaction $transaction) {
                $customerName = $transaction->customer_name;

                if (!$customerName && $transaction->customer) {
                    $customerName = trim(sprintf(
                        '%s %s',
                        $transaction->customer->first_name,
                        $transaction->customer->last_name
                    ));
                }

                return [
                    'transactionNumber' => $transaction->transaction_number,
                    'transactionDate' => optional($transaction->transaction_date)->toIso8601String(),
                    'customerName' => $customerName ?: 'Walk-in Customer',
                    'total' => round((float) $transaction->total, 2),
                    'paymentMethod' => $transaction->payment_method,
                    'status' => $transaction->status,
                    'itemsCount' => collect($transaction->items ?? [])->sum('quantity'),
                ];
            })
            ->toArray();
    }
}
