<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$view = $ctrl->index(Request::create('/financial', 'GET', ['year_from'=>2026,'year_to'=>2026]));
$html = $view->render();
foreach (['detalle-familias'=>'link familias','detalle-productos'=>'link productos','detalle-clientes'=>'link clientes','detalle-ppv'=>'link ppv','Ver todo'=>'texto Ver todo','info-btn'=>'class info-btn'] as $k=>$label) {
    echo str_pad($label,16).": ".(strpos($html,$k)!==false?'OK':'FALTA')."\n";
}
