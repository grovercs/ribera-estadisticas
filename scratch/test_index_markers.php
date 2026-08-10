<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$view = $ctrl->index(Request::create('/financial', 'GET', ['year_from'=>2026,'year_to'=>2026]));
$html = $view->render();
foreach (['id="panelGlosario"'=>'Glosario panel','id="subnav"'=>'Sub-nav','sortable-th'=>'Tabla ordenable','data-gloss="glos-revenue"'=>'Icono info','id="resumen"'=>'ancla resumen','id="compras"'=>'ancla compras','verTodo'=>'enlaces Ver todo'] as $k=>$label) {
    echo str_pad($label,22).": ".(strpos($html,$k)!==false?'OK':'FALTA')."\n";
}
