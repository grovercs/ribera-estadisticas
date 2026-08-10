<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ctrl = app(App\Http\Controllers\StoreDashboardController::class);

foreach (['mes_actual', 'year_actual', 'year_anterior'] as $p) {
    $req = Illuminate\Http\Request::create('/store-dashboard/detalle-facturas-compras', 'GET', ['periodo' => $p, 'year' => 2026]);
    $resp = $ctrl->detalleFacturasCompras($req);
    $j = json_decode($resp->getContent(), true);
    $sum = array_sum(array_column($j['data'] ?? [], 'total'));
    printf("%-22s success=%s count=%d sum=%.2f\n", $p, $j['success'] ? 'ok' : 'ERR', count($j['data'] ?? []), $sum);
    if (!($j['success'] ?? false)) echo "  ERROR: " . ($j['error'] ?? '') . "\n";
}

// Render the blade view to check it compiles
echo "\n=== Blade compile check ===\n";
try {
    $html = view('store-dashboard.index')->with([
        'tiendas' => [], 'totales' => [], 'facturasCompras' => [], 'pagosPendientes' => [],
        'year' => 2026, 'periodo' => 'year',
    ])->render();
    echo "OK render (" . strlen($html) . " bytes)\n";
} catch (\Throwable $e) {
    echo "BLADE ERR: " . $e->getMessage() . "\n";
}