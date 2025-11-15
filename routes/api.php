<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLoyaltyTransactionController;
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
});
