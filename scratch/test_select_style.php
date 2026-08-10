<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$html = $ctrl->index(Request::create('/financial','GET',['year_from'=>2026,'year_to'=>2026]))->render();
echo "select rule: ".(strpos($html,'padding-right: 1.9rem')!==false?'OK':'FALTA')."\n";
echo "appearance none: ".(strpos($html,'appearance: none !important')!==false?'OK':'FALTA')."\n";
echo "chevron svg: ".(strpos($html,'M6 9l6 6 6-6')!==false?'OK':'FALTA')."\n";
echo "len=".strlen($html)."\n";
