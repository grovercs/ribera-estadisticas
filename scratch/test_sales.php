<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Autenticar un usuario para saltar el middleware auth
$user = \App\Models\User::first();
if (!$user) {
    echo "ERROR: No hay usuarios en la base de datos.\n";
    exit(1);
}
\Illuminate\Support\Facades\Auth::login($user);

$controller = $app->make(\App\Http\Controllers\OrderController::class);

// Test 1: index()
$request = Illuminate\Http\Request::create('/sales', 'GET', [
    'year_from' => 2024,
    'year_to' => 2025,
    'month_from' => 1,
    'month_to' => 12,
]);

$response = $controller->index($request);
$html = $response instanceof Illuminate\View\View ? $response->render() : $response->getContent();

if (strpos($html, 'Módulo de Ventas') !== false) {
    echo "OK: index() renderiza correctamente.\n";
} else {
    echo "ERROR: index() no renderiza el título esperado.\n";
    exit(1);
}

// Test 2: lines() con una venta real
$sample = DB::connection('erp')->select("
    SELECT TOP 1 cod_venta, tipo_venta, cod_empresa, cod_caja
    FROM hist_ventas_cabecera
    WHERE fecha_venta >= '2024-01-01'
")[0] ?? null;

if ($sample) {
    $linesRequest = Illuminate\Http\Request::create('/sales/lines', 'GET', [
        'cod_venta' => $sample->cod_venta,
        'tipo_venta' => $sample->tipo_venta,
        'cod_empresa' => $sample->cod_empresa,
        'cod_caja' => $sample->cod_caja,
    ]);

    $linesResponse = $controller->lines($linesRequest);
    $lines = $linesResponse->getData(true);

    if (is_array($lines) && count($lines) > 0) {
        echo "OK: lines() devuelve " . count($lines) . " líneas para venta {$sample->cod_venta}.\n";
    } else {
        echo "AVISO: lines() devolvió vacío para venta {$sample->cod_venta}.\n";
    }
} else {
    echo "AVISO: no se encontró venta de prueba.\n";
}

// Test 3: CSV export
$csvRequest = Illuminate\Http\Request::create('/sales', 'GET', [
    'year_from' => 2025,
    'year_to' => 2025,
    'month_from' => 1,
    'month_to' => 1,
    'export' => 'csv',
]);
$csvResponse = $controller->index($csvRequest);
if ($csvResponse instanceof Symfony\Component\HttpFoundation\StreamedResponse) {
    echo "OK: exportación CSV devuelve StreamedResponse.\n";
} else {
    echo "ERROR: exportación CSV no devuelve StreamedResponse.\n";
    exit(1);
}

echo "\nPruebas completadas.\n";
