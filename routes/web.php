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
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\StoreDashboardController;

// Auth routes (public)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sales', [OrderController::class, 'index'])->name('sales.index');
    Route::get('/sales/lines', [OrderController::class, 'lines'])->name('sales.lines');
    Route::get('/stock', [ProductController::class, 'index'])->name('stock.index');
    Route::get('/stock/subfamilies', [ProductController::class, 'subfamilies'])->name('stock.subfamilies');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients');
    Route::get('/reports/comparison', [ReportController::class, 'comparison'])->name('reports.comparison');
    Route::get('/api/subfamilies', [ReportController::class, 'getSubfamilies'])->name('api.subfamilies');
    Route::get('/families', [FamilyController::class, 'index'])->name('families');
    Route::get('/families/{cod_familia}', [FamilyController::class, 'show'])->name('families.show');
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
    Route::get('/financial', [FinancialController::class, 'index'])->name('financial');
    Route::get('/financial/detalle-familias', [FinancialController::class, 'detalleFamilias'])->name('financial.detalle-familias');
    Route::get('/financial/detalle-productos', [FinancialController::class, 'detalleProductos'])->name('financial.detalle-productos');
    Route::get('/financial/detalle-clientes', [FinancialController::class, 'detalleClientes'])->name('financial.detalle-clientes');
    Route::get('/financial/detalle-ppv', [FinancialController::class, 'detallePpv'])->name('financial.detalle-ppv');
    Route::get('/financial/evolucion-data', [FinancialController::class, 'evolucionData'])->name('financial.evolucion-data');
    Route::get('/store-dashboard', [StoreDashboardController::class, 'index'])->name('store-dashboard');
    Route::get('/store-dashboard/detalle-impagados', [StoreDashboardController::class, 'detalleImpagados'])->name('store-dashboard.detalle-impagados');
    Route::get('/store-dashboard/detalle-pagos', [StoreDashboardController::class, 'detallePagos'])->name('store-dashboard.detalle-pagos');
    Route::get('/store-dashboard/detalle-facturas-compras', [StoreDashboardController::class, 'detalleFacturasCompras'])->name('store-dashboard.detalle-facturas-compras');
});
