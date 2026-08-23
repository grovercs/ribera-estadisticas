<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoreDashboardController;
use App\Http\Middleware\RestrictToLocalNetwork;

// Local API Routes (restricted strictly to local network/loopback)
Route::middleware([RestrictToLocalNetwork::class])->prefix('local')->group(function () {
    Route::get('/health', [StoreDashboardController::class, 'apiHealth'])->name('api.local.health');
    Route::get('/dashboard-summary', [StoreDashboardController::class, 'apiSummary'])->name('api.local.dashboard-summary');
});
