<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$view = $ctrl->index(Request::create('/financial', 'GET', ['year_from'=>2026,'year_to'=>2026]));
$html = $view->render();
foreach (['evoPresets'=>'contenedor presets','evo-preset'=>'botones preset','evoApply'=>'botón Aplicar','evolucion-data'=>'ruta AJAX','evolutionChart'=>'var gráfico','evoLoad('=>'función AJAX'] as $k=>$label) {
    echo str_pad($label,20).": ".(strpos($html,$k)!==false?'OK':'FALTA')."\n";
}
echo "len=".strlen($html)."\n";
