<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\ReportController::class);
cache()->flush();
$html = $ctrl->comparison(Request::create('/reports/comparison','GET',['year1'=>2025,'year2'=>2026,'compare'=>1]))->render();
// Extraer el trozo del script entre productChart y el siguiente new Chart
$phpStart = strpos($html, 'productCtx = document');
$snippet = substr($html, $phpStart, 600);
echo "---- product chart JS snippet ----\n".$snippet."\n";
// Buscar el @json de topProductsCombined
$i = strpos($html, 'topProductsCombined');
echo "\n---- presence ----\n";
echo "topProductsCombined in html: ".($i!==false?'yes':'no')."\n";
