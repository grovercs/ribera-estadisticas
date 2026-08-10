<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$ctrl = app(App\Http\Controllers\FinancialController::class);
$req = Request::create('/financial', 'GET');
$resp = $ctrl->index($req);
$data = $resp instanceof Illuminate\Contracts\View\View ? $resp->getData() : [];
$p = $data['purchase'] ?? null;
if (!$p) { echo "NO purchase data\n"; var_dump(array_keys($data)); exit; }
printf("=== KPI compra (%s) ===\n", $p['periodoA']);
printf("  importe=%.2f articulos=%d proveedores=%d lineas=%d\n", $p['kpi']['importe'], $p['kpi']['articulos'], $p['kpi']['proveedores'], $p['kpi']['lineas']);
printf("=== Indice (base %s), %d meses ===\n", $p['indexBaseLabel'], count($p['index']));
foreach ($p['index'] as $k=>$v) printf("  %s = %.1f\n", $k, $v);
printf("=== PPV A=%s vs B=%s ===\n", $p['periodoA'], $p['periodoB']);
printf("  SUBIDAS (%d):\n", count($p['ppvIncreases']));
foreach (array_slice($p['ppvIncreases'],0,5) as $i) printf("    %-14s pB=%.3f pA=%.3f var=%+.1f%%  %s\n", $i['cod'], $i['pB'], $i['pA'], $i['var'], substr($i['desc'],0,30));
printf("  BAJADAS (%d):\n", count($p['ppvDecreases']));
foreach (array_slice($p['ppvDecreases'],0,5) as $i) printf("    %-14s pB=%.3f pA=%.3f var=%+.1f%%  %s\n", $i['cod'], $i['pB'], $i['pA'], $i['var'], substr($i['desc'],0,30));