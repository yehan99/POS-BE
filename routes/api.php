<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLoyaltyTransactionController;
use App\Http\Controllers\Hardware\HardwareConfigController;
use App\Http\Controllers\Hardware\HardwareStatusController;
use App\Http\Controllers\Hardware\ReceiptTemplateController;
use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\InventoryLocationController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockAlertsController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\POS\SalesTransactionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('google', [AuthController::class, 'login'])->name('google');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

    Route::middleware('auth.jwt')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth.jwt')->group(function () {
    Route::get('users/options', [UserController::class, 'options'])->name('users.options');
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    Route::apiResource('users', UserController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::get('customers/statistics', [CustomerController::class, 'statistics'])->name('customers.statistics');
    Route::get('customers/generate-code', [CustomerController::class, 'generateCode'])->name('customers.generate-code');
    Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDelete'])->name('customers.bulk-delete');
    Route::get('customers/{customer}/loyalty-transactions', [CustomerLoyaltyTransactionController::class, 'index'])->name('customers.loyalty-transactions.index');
    Route::post('customers/{customer}/loyalty-transactions', [CustomerLoyaltyTransactionController::class, 'store'])->name('customers.loyalty-transactions.store');
    Route::apiResource('customers', CustomerController::class);

    Route::get('categories/active', [ProductCategoryController::class, 'active'])->name('categories.active');
    Route::get('categories/tree', [ProductCategoryController::class, 'tree'])->name('categories.tree');
    Route::put('categories/reorder', [ProductCategoryController::class, 'reorder'])->name('categories.reorder');
    Route::apiResource('categories', ProductCategoryController::class);

    Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('products/out-of-stock', [ProductController::class, 'outOfStock'])->name('products.out-of-stock');
    Route::get('products/generate-sku', [ProductController::class, 'generateSku'])->name('products.generate-sku');
    Route::get('products/generate-barcode', [ProductController::class, 'generateBarcode'])->name('products.generate-barcode');
    Route::get('products/check-sku', [ProductController::class, 'checkSku'])->name('products.check-sku');
    Route::get('products/check-barcode', [ProductController::class, 'checkBarcode'])->name('products.check-barcode');
    Route::get('products/barcode/{barcode}', [ProductController::class, 'getByBarcode'])->name('products.get-by-barcode');
    Route::post('products/bulk-delete', [ProductController::class, 'bulkDelete'])->name('products.bulk-delete');
    Route::post('products/bulk-import', [ProductController::class, 'bulkImport'])->name('products.bulk-import');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('products/statistics', [ProductController::class, 'statistics'])->name('products.statistics');
    Route::get('products/brands', [ProductController::class, 'brands'])->name('products.brands');
    Route::get('products/tags', [ProductController::class, 'tags'])->name('products.tags');
    Route::patch('products/{product}/stock', [ProductController::class, 'updateStock'])->name('products.update-stock');
    Route::post('products/{product}/upload-image', [ProductController::class, 'uploadImage'])->name('products.upload-image');
    Route::delete('products/{product}/delete-image', [ProductController::class, 'deleteImage'])->name('products.delete-image');
    Route::apiResource('products', ProductController::class);

    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('dashboard', [SupplierController::class, 'dashboard'])->name('dashboard');
        Route::get('generate-code', [SupplierController::class, 'generateCode'])->name('generate-code');
        Route::get('active', [SupplierController::class, 'active'])->name('active');
        Route::post('bulk-delete', [SupplierController::class, 'bulkDelete'])->name('bulk-delete');
        Route::get('export', [SupplierController::class, 'export'])->name('export');
        Route::post('import', [SupplierController::class, 'import'])->name('import');
        Route::get('{supplier}/statistics', [SupplierController::class, 'statistics'])->name('statistics');
        Route::get('{supplier}/purchase-history', [SupplierController::class, 'purchaseHistory'])->name('purchase-history');
    });

    Route::apiResource('suppliers', SupplierController::class);

    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Dashboard endpoints
        Route::get('dashboard', [InventoryDashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/metrics', [InventoryDashboardController::class, 'metrics'])->name('dashboard.metrics');
        Route::get('dashboard/pipeline', [InventoryDashboardController::class, 'pipeline'])->name('dashboard.pipeline');
        Route::get('dashboard/exceptions', [InventoryDashboardController::class, 'exceptions'])->name('dashboard.exceptions');
        Route::get('dashboard/alerts', [InventoryDashboardController::class, 'alerts'])->name('dashboard.alerts');

        // Stock Alerts endpoints
        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/', [StockAlertsController::class, 'index'])->name('index');
            Route::get('summary', [StockAlertsController::class, 'summary'])->name('summary');
            Route::put('{id}/acknowledge', [StockAlertsController::class, 'acknowledge'])->name('acknowledge');
            Route::put('{id}/resolve', [StockAlertsController::class, 'resolve'])->name('resolve');
            Route::post('bulk-resolve', [StockAlertsController::class, 'bulkResolve'])->name('bulk-resolve');
        });

        Route::apiResource('locations', InventoryLocationController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::prefix('adjustments')->name('adjustments.')->group(function () {
            Route::get('dashboard', [StockAdjustmentController::class, 'dashboard'])->name('dashboard');
            Route::get('export', [StockAdjustmentController::class, 'export'])->name('export');
            Route::get('generate-number', [StockAdjustmentController::class, 'generateNumber'])->name('generate-number');
            Route::post('bulk', [StockAdjustmentController::class, 'bulkStore'])->name('bulk');
            Route::post('{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('approve');
            Route::post('{stock_adjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('reject');
        });

        Route::apiResource('adjustments', StockAdjustmentController::class)
            ->only(['index', 'store', 'show']);

        Route::prefix('stock-transfers')->name('stock-transfers.')->group(function () {
            Route::get('dashboard', [StockTransferController::class, 'dashboard'])->name('dashboard');
            Route::get('export', [StockTransferController::class, 'export'])->name('export');
            Route::get('generate-number', [StockTransferController::class, 'generateNumber'])->name('generate-number');
            Route::post('{stock_transfer}/approve', [StockTransferController::class, 'approve'])->name('approve');
            Route::post('{stock_transfer}/ship', [StockTransferController::class, 'ship'])->name('ship');
            Route::post('{stock_transfer}/receive', [StockTransferController::class, 'receive'])->name('receive');
            Route::post('{stock_transfer}/cancel', [StockTransferController::class, 'cancel'])->name('cancel');
        });

        Route::apiResource('stock-transfers', StockTransferController::class)
            ->only(['index', 'store', 'show']);
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('dashboard', [PurchaseOrderController::class, 'dashboard'])->name('dashboard');
        Route::get('statistics', [PurchaseOrderController::class, 'statistics'])->name('statistics');
        Route::get('generate-number', [PurchaseOrderController::class, 'generateNumber'])->name('generate-number');
        Route::get('export', [PurchaseOrderController::class, 'export'])->name('export');
        Route::get('{purchase_order}/pdf', [PurchaseOrderController::class, 'pdf'])->name('pdf');
        Route::get('{purchase_order}/grns', [PurchaseOrderController::class, 'grns'])->name('grns');
        Route::post('{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
        Route::post('{purchase_order}/ordered', [PurchaseOrderController::class, 'ordered'])->name('ordered');
        Route::post('{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
        Route::post('{purchase_order}/send', [PurchaseOrderController::class, 'send'])->name('send');
        Route::post('{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
    });

    Route::apiResource('purchase-orders', PurchaseOrderController::class);

    // Hardware Configuration endpoints
    Route::prefix('hardware/devices')->name('hardware.devices.')->group(function () {
        Route::get('connection-status', [HardwareConfigController::class, 'connectionStatus'])->name('connection-status');
        Route::delete('clear-all', [HardwareConfigController::class, 'clearAll'])->name('clear-all');
        Route::post('bulk-delete', [HardwareConfigController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('{id}/test', [HardwareConfigController::class, 'testConnection'])->name('test');
        Route::put('{id}/toggle', [HardwareConfigController::class, 'toggleEnabled'])->name('toggle');
    });

    Route::apiResource('hardware/devices', HardwareConfigController::class);

    // Hardware Status Dashboard endpoints
    Route::prefix('hardware/status')->name('hardware.status.')->group(function () {
        Route::get('dashboard', [HardwareStatusController::class, 'dashboard'])->name('dashboard');
        Route::get('system-health', [HardwareStatusController::class, 'systemHealth'])->name('system-health');
        Route::get('device-health', [HardwareStatusController::class, 'deviceHealth'])->name('device-health');
        Route::get('alerts', [HardwareStatusController::class, 'alerts'])->name('alerts');
        Route::get('events', [HardwareStatusController::class, 'events'])->name('events');
        Route::get('statistics-by-type', [HardwareStatusController::class, 'statisticsByType'])->name('statistics-by-type');
    });

    // Receipt Template endpoints
    Route::prefix('hardware/receipt-templates')->name('hardware.receipt-templates.')->group(function () {
        Route::get('/', [ReceiptTemplateController::class, 'index'])->name('index');
        Route::post('/', [ReceiptTemplateController::class, 'store'])->name('store');
        Route::get('/default', [ReceiptTemplateController::class, 'getDefault'])->name('default');
        Route::get('/default-structure', [ReceiptTemplateController::class, 'getDefaultStructure'])->name('default-structure');
        Route::get('/{id}', [ReceiptTemplateController::class, 'show'])->name('show');
        Route::put('/{id}', [ReceiptTemplateController::class, 'update'])->name('update');
        Route::delete('/{id}', [ReceiptTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/set-default', [ReceiptTemplateController::class, 'setDefault'])->name('set-default');
        Route::post('/{id}/duplicate', [ReceiptTemplateController::class, 'duplicate'])->name('duplicate');
    });

    // POS Sales Transaction endpoints
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [SalesTransactionController::class, 'index'])->name('index');
        Route::post('/', [SalesTransactionController::class, 'store'])->name('store');
        Route::get('/search', [SalesTransactionController::class, 'search'])->name('search');
        Route::get('/summary', [SalesTransactionController::class, 'summary'])->name('summary');
        Route::get('/export', [SalesTransactionController::class, 'export'])->name('export');
        Route::get('/number/{transactionNumber}', [SalesTransactionController::class, 'showByNumber'])->name('show-by-number');
        Route::get('/{id}', [SalesTransactionController::class, 'show'])->name('show');
        Route::post('/{id}/refund', [SalesTransactionController::class, 'refund'])->name('refund');
        Route::post('/{id}/cancel', [SalesTransactionController::class, 'cancel'])->name('cancel');
    });
});
