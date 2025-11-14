<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
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
    Route::apiResource('users', UserController::class)
        ->only(['index', 'store', 'show', 'update']);
});
