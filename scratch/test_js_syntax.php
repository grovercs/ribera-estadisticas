<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\ReportController::class);
cache()->flush();
$html = $ctrl->comparison(Request::create('/reports/comparison','GET',['year1'=>2025,'year2'=>2026,'compare'=>1]))->render();
// Extraer el contenido del <script> dentro del @push scripts (el que empieza con 'let chartType')
$start = strpos($html, "let chartType = 'bar';");
$end = strpos($html, '</script>', $start);
$js = substr($html, $start, $end - $start);
file_put_contents(__DIR__.'/cmp_script.js', $js);
echo "script length=".strlen($js)."\n";
echo "saved cmp_script.js\n";
