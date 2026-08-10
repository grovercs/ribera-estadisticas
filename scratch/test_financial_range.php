<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
foreach ([
    '2024-2026' => ['year_from'=>2024,'year_to'=>2026],
    '2025' => ['year_from'=>2025,'year_to'=>2025],
] as $label => $params) {
    $req = Request::create('/financial?'.http_build_query($params), 'GET');
    $view = $ctrl->index($req);
    $p = $view->getData()['purchase'];
    echo "=== $label ===\n";
    echo "  periodoA={$p['periodoA']} vs B={$p['periodoB']}\n";
    echo "  KPI importe=" . number_format($p['kpi']['importe'], 0, ',', '.') . " art={$p['kpi']['articulos']} prov={$p['kpi']['proveedores']}\n";
    echo "  index base={$p['indexBaseLabel']} meses=" . count($p['index']) . "\n";
    $vals = array_values($p['index']);
    if ($vals) echo "  index rango: min=" . min($vals) . " max=" . max($vals) . " first=" . reset($vals) . " last=" . end($vals) . "\n";
    echo "  subidas=" . count($p['ppvIncreases']) . " bajadas=" . count($p['ppvDecreases']) . "\n";
    if ($p['ppvIncreases']) {
        $top = $p['ppvIncreases'][0];
        echo "  top subida: {$top['cod']} " . number_format($top['var'], 1) . "% {$top['desc']}\n";
    }
}