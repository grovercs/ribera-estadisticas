<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Autenticar un usuario
$user = \App\Models\User::first();
if (!$user) {
    echo "ERROR: No hay usuarios en la base de datos.\n";
    exit(1);
}
\Illuminate\Support\Facades\Auth::login($user);

$controller = $app->make(\App\Http\Controllers\ProductController::class);

// Test 1: index() por defecto (top facturación últimos 12 meses)
$start = microtime(true);
$request = Illuminate\Http\Request::create('/stock', 'GET');
$response = $controller->index($request);
$elapsed = round(microtime(true) - $start, 2);

$html = $response instanceof Illuminate\View\View ? $response->render() : $response->getContent();

if (strpos($html, 'Análisis de Stock') !== false) {
    echo "OK: index() renderiza correctamente ({$elapsed}s).\n";
} else {
    echo "ERROR: index() no renderiza el título esperado.\n";
    exit(1);
}

// Test 2: búsqueda por familia
$request2 = Illuminate\Http\Request::create('/stock', 'GET', [
    'cod_familia' => '1010',
    'sales_months' => 12,
]);
$response2 = $controller->index($request2);
$html2 = $response2 instanceof Illuminate\View\View ? $response2->render() : $response2->getContent();
if (strpos($html2, 'Análisis de Stock') !== false) {
    echo "OK: index() con familia renderiza.\n";
} else {
    echo "ERROR: index() con familia falla.\n";
    exit(1);
}

// Test 3: subfamilies AJAX
$subReq = Illuminate\Http\Request::create('/stock/subfamilies', 'GET', ['cod_familia' => '1010']);
$subResponse = $controller->subfamilies($subReq);
$subs = $subResponse->getData(true);
if (is_array($subs)) {
    echo "OK: subfamilies() devuelve " . count($subs) . " subfamilias.\n";
} else {
    echo "ERROR: subfamilies() no devolvió array.\n";
    exit(1);
}

echo "\nPruebas completadas.\n";
