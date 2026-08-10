<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\ReportController::class);
cache()->flush();
$t=microtime(true);
$view = $ctrl->comparison(Request::create('/reports/comparison','GET',['year1'=>2025,'year2'=>2026,'compare'=>1]));
$d = $view->getData();
$bf = $d['results']['byFamily'] ?? null;
echo "elapsed=".round(microtime(true)-$t,2)."s\n";
echo "byFamily count=".count($bf ?? [])."\n";
if (!empty($bf)) {
  foreach(array_slice($bf,0,3) as $f) printf("  %-28s y1=%s y2=%s Δ=%+.1f%%\n", substr($f['familia'],0,26), number_format($f['y1_revenue'],0,',','.'), number_format($f['y2_revenue'],0,',','.'), $f['growth']);
  $y2s = array_column($bf,'y2_revenue'); $s=$y2s;
  rsort($s);
  echo "sorted by y2 desc: ".($y2s===$s?'yes':'no')."\n";
}
$html=$view->render();
echo "len=".strlen($html)."\n";
echo "title comparativa: ".(strpos($html,'comparativa 2025 vs 2026')!==false?'OK':'FALTA')."\n";
echo "famSortToggle: ".(strpos($html,'famSortToggle')!==false?'OK':'FALTA')."\n";
echo "json y1_revenue: ".(strpos($html,'y1_revenue')!==false?'OK':'FALTA')."\n";
