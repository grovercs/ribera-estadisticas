<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::first();
if (!$user) {
    echo "ERROR: No hay usuarios en la base de datos.\n";
    exit(1);
}
\Illuminate\Support\Facades\Auth::login($user);

$controller = $app->make(\App\Http\Controllers\DashboardController::class);

\Illuminate\Support\Facades\Cache::flush();

// Test 1: dashboard normal
$start = microtime(true);
$request = Illuminate\Http\Request::create('/', 'GET', [
    'year_from' => 2025,
    'year_to' => 2025,
    'month_from' => 1,
    'month_to' => 12,
]);

$response = $controller->index($request);
$elapsed = round(microtime(true) - $start, 2);

if ($response instanceof Illuminate\View\View) {
    $html = $response->render();
    if (strpos($html, 'Resumen') !== false && strpos($html, 'salesChart') !== false) {
        echo "OK: Dashboard renderiza correctamente ({$elapsed}s).\n";
    } else {
        echo "ERROR: Dashboard no contiene elementos esperados.\n";
        exit(1);
    }
} else {
    echo "ERROR: Respuesta inesperada.\n";
    exit(1);
}

// Test 2: ocultar productos sin stock
\Illuminate\Support\Facades\Cache::flush();
$request2 = Illuminate\Http\Request::create('/', 'GET', [
    'year_from' => 2025,
    'year_to' => 2025,
    'month_from' => 1,
    'month_to' => 12,
    'hide_no_stock' => 1,
]);
$response2 = $controller->index($request2);
$viewData = $response2->getData();

if (empty($viewData['hideNoStock'])) {
    echo "ERROR: hideNoStock debería ser true.\n";
    exit(1);
}

$zeroStock = array_filter($viewData['topProducts'], fn($p) => ($p['stock_total'] ?? 0) <= 0);
if (empty($zeroStock)) {
    echo "OK: Con 'hide_no_stock=1' no hay productos sin stock en el top.\n";
} else {
    echo "ERROR: Aún aparecen " . count($zeroStock) . " productos sin stock con el filtro activo.\n";
    exit(1);
}

echo "\nPruebas completadas.\n";
