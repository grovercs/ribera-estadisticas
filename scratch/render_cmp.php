<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\ReportController::class);
cache()->flush();
$html = $ctrl->comparison(Request::create('/reports/comparison','GET',['year1'=>2025,'year2'=>2026,'compare'=>1]))->render();
file_put_contents(__DIR__.'/cmp.html', $html);
echo "saved cmp.html len=".strlen($html)."\n";
