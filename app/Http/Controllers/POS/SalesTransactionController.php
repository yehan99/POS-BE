<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\SalesTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesTransactionController extends Controller
{
    /**
     * Get all transactions with pagination and optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SalesTransaction::with(['customer', 'cashier', 'tenant'])
                ->orderBy('transaction_date', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_method')) {
                $query->paymentMethod($request->payment_method);
            }

            if ($request->has('cashier_id')) {
                $query->byCashier($request->cashier_id);
            }

            if ($request->has('customer_id')) {
                $query->byCustomer($request->customer_id);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            // Pagination
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);

            $total = $query->count();
            $transactions = $query->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'total' => $total,
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new sales transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'amount_paid' => 'required|numeric|min:0',
            'change' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,mobile,split',
            'payment_details' => 'nullable|array',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string',
            'cashier_id' => 'required|exists:users,id',
            'cashier_name' => 'required|string',
            'tenant_id' => 'required|exists:tenants,id',
            'store_name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $transaction = SalesTransaction::create($request->all());

            // TODO: Update product stock levels based on items sold
            // foreach ($request->items as $item) {
            //     // Deduct stock from inventory
            // }

            // TODO: Update customer loyalty points if customer_id exists
            // if ($request->customer_id) {
            //     // Add loyalty points
            // }

            DB::commit();

            $transaction->load(['customer', 'cashier', 'tenant']);

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => 'Transaction created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single transaction by ID.
     */
    public function show($id): JsonResponse
    {
        try {
            $transaction = SalesTransaction::with(['customer', 'cashier', 'tenant', 'refundedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    /**
     * Get transaction by transaction number.
     */
    public function showByNumber($transactionNumber): JsonResponse
    {
        try {
            $transaction = SalesTransaction::with(['customer', 'cashier', 'tenant'])
                ->where('transaction_number', $transactionNumber)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    /**
     * Search transactions with filters.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = SalesTransaction::with(['customer', 'cashier', 'tenant'])
                ->orderBy('transaction_date', 'desc');

            // Apply all filters
            if ($request->filled('startDate')) {
                $query->where('transaction_date', '>=', $request->startDate);
            }

            if ($request->filled('endDate')) {
                $query->where('transaction_date', '<=', $request->endDate);
            }

            if ($request->filled('paymentMethod')) {
                $query->paymentMethod($request->paymentMethod);
            }

            if ($request->filled('cashierId')) {
                $query->byCashier($request->cashierId);
            }

            if ($request->filled('customerId')) {
                $query->byCustomer($request->customerId);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('minAmount')) {
                $query->where('total', '>=', $request->minAmount);
            }

            if ($request->filled('maxAmount')) {
                $query->where('total', '<=', $request->maxAmount);
            }

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Pagination
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);

            $total = $query->count();
            $transactions = $query->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction summary/statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $query = SalesTransaction::query();

            // Apply date filters
            if ($request->filled('startDate')) {
                $query->where('transaction_date', '>=', $request->startDate);
            }

            if ($request->filled('endDate')) {
                $query->where('transaction_date', '<=', $request->endDate);
            }

            // Calculate summary
            $completedTransactions = (clone $query)->completed();

            $summary = [
                'totalTransactions' => $completedTransactions->count(),
                'totalSales' => $completedTransactions->sum('total'),
                'totalRefunds' => (clone $query)->refunded()->sum('total'),
                'cashSales' => $completedTransactions->where('payment_method', 'cash')->sum('total'),
                'cardSales' => $completedTransactions->where('payment_method', 'card')->sum('total'),
                'mobileSales' => $completedTransactions->where('payment_method', 'mobile')->sum('total'),
                'averageTransactionValue' => $completedTransactions->count() > 0
                    ? $completedTransactions->avg('total')
                    : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund a transaction.
     */
    public function refund(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = SalesTransaction::findOrFail($id);

            if (!$transaction->canRefund()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction cannot be refunded'
                ], 400);
            }

            DB::beginTransaction();

            $transaction->update([
                'status' => 'refunded',
                'refund_reason' => $request->reason,
                'refunded_at' => now(),
                'refunded_by' => auth()->id(),
            ]);

            // TODO: Restore product stock levels
            // TODO: Deduct customer loyalty points if applicable

            DB::commit();

            $transaction->load(['customer', 'cashier', 'refundedBy']);

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => 'Transaction refunded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to refund transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a transaction.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = SalesTransaction::findOrFail($id);

            if (!$transaction->canCancel()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction cannot be cancelled'
                ], 400);
            }

            DB::beginTransaction();

            $transaction->update([
                'status' => 'cancelled',
                'refund_reason' => $request->reason,
            ]);

            // TODO: Restore product stock levels
            // TODO: Deduct customer loyalty points if applicable

            DB::commit();

            $transaction->load(['customer', 'cashier']);

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => 'Transaction cancelled successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export transactions to CSV.
     */
    public function export(Request $request)
    {
        try {
            $query = SalesTransaction::with(['customer', 'cashier'])
                ->orderBy('transaction_date', 'desc');

            // Apply filters
            if ($request->filled('startDate')) {
                $query->where('transaction_date', '>=', $request->startDate);
            }

            if ($request->filled('endDate')) {
                $query->where('transaction_date', '<=', $request->endDate);
            }

            $transactions = $query->get();

            // Generate CSV
            $filename = 'transactions_' . now()->format('Y-m-d_His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($transactions) {
                $file = fopen('php://output', 'w');

                // CSV headers
                fputcsv($file, [
                    'Transaction Number',
                    'Date',
                    'Customer',
                    'Cashier',
                    'Payment Method',
                    'Subtotal',
                    'Discount',
                    'Tax',
                    'Total',
                    'Status'
                ]);

                // CSV data
                foreach ($transactions as $transaction) {
                    fputcsv($file, [
                        $transaction->transaction_number,
                        $transaction->transaction_date->format('Y-m-d H:i:s'),
                        $transaction->customer_name ?? 'Walk-in',
                        $transaction->cashier_name,
                        ucfirst($transaction->payment_method),
                        number_format($transaction->subtotal, 2),
                        number_format($transaction->discount_amount, 2),
                        number_format($transaction->tax_amount, 2),
                        number_format($transaction->total, 2),
                        ucfirst($transaction->status)
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
