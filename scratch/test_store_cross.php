<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
// Proceso A: poblar
cache()->flush();
$ctrl = app(App\Http\Controllers\StoreDashboardController::class);
$ctrl->index(Request::create('/store-dashboard', 'GET'));
echo "A: store-dashboard cache poblado\n";
// Proceso B (mismo script, pero forzamos lectura fresca simulando otro request):
// como remember devuelve en caliente, simulamos borrando instancia y re-leyendo cache
$data = cache()->get('store_dashboard_data_all');
echo "B: cache " . ($data ? 'hit' : 'miss') . "\n";
if ($data) {
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            foreach ($v as $kk => $vv) {
                if (is_object($vv)) { echo "  OBJ en $k.$kk: " . get_class($vv) . "\n"; }
            }
        }
    }
}
// render real
$view = $ctrl->index(Request::create('/store-dashboard', 'GET'));
$html = $view->render();
echo "render OK len=" . strlen($html) . "\n";