<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ReportController;

// Auth routes (public)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sales', [OrderController::class, 'index'])->name('sales');
    Route::get('/stock', [ProductController::class, 'index'])->name('stock');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients');
    Route::get('/reports/comparison', [ReportController::class, 'comparison'])->name('reports.comparison');
    Route::get('/families', [FamilyController::class, 'index'])->name('families');
    Route::get('/families/{cod_familia}', [FamilyController::class, 'show'])->name('families.show');
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
});
