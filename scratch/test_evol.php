<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
cache()->flush();
$cases = [
  ['Este año 2026', ['year_from'=>2026,'year_to'=>2026]],
  ['Año anterior 2025', ['year_from'=>2025,'year_to'=>2025]],
  ['Últimos 2 años', ['year_from'=>2025,'year_to'=>2026]],
  ['Últimos 3 años', ['year_from'=>2024,'year_to'=>2026]],
  ['Todo el histórico', ['year_from'=>'all','year_to'=>'all']],
  ['Con meses ene-mar 2026', ['year_from'=>2026,'year_to'=>2026,'month_from'=>1,'month_to'=>3]],
];
foreach ($cases as [$label,$p]) {
  $t=microtime(true);
  $resp = $ctrl->evolucionData(Request::create('/financial/evolucion-data','GET',$p));
  $j = json_decode($resp->getContent(), true);
  printf("%-26s pts=%d first=%s last=%s rev0=%s %.2fs\n",
    $label, count($j['labels']),
    $j['labels'][0] ?? '-', $j['labels'][count($j['labels'])-1] ?? '-',
    $j['revenue'][0] ?? '-', microtime(true)-$t);
}
