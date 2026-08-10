<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\ReportController::class);
cache()->flush();
$view = $ctrl->comparison(Request::create('/reports/comparison','GET',['year1'=>2025,'year2'=>2026,'compare'=>1]));
$d = $view->getData();
$r = $d['results'];
echo "topProductsCombined count=".count($r['topProductsCombined'] ?? [])."\n";
echo "topProducts[year1] count=".count($r['topProducts'][2025] ?? [])."\n";
echo "topProducts[year2] count=".count($r['topProducts'][2026] ?? [])."\n";
if (!empty($r['topProductsCombined'])) {
  $p = $r['topProductsCombined'][0];
  echo "first type: ".gettype($p)."\n";
  echo "first keys: ".implode(',', array_keys((array)$p))."\n";
  echo "first: cod={$p->cod_articulo} desc=".substr($p->descripcion ?? '?',0,40)." y1={$p->year1_revenue} y2={$p->year2_revenue}\n";
}
