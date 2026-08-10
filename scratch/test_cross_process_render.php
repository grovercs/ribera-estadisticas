<?php
// Proceso B: como una petición web nueva que lee el caché poblado por otro proceso.
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$view = $ctrl->index(Request::create('/financial', 'GET'));
// Verificar que el dato viene del cache (no recién computado): el controlador usa remember.
$data = $view->getData();
$fams = $data['marginByFamily'] ?? [];
echo "marginByFamily count=" . count($fams) . " primer tipo: " . (is_array($fams[0] ?? null) ? 'array' : get_class($fams[0] ?? new stdClass)) . "\n";
$html = $view->render();
echo "render OK, len=" . strlen($html) . "\n";
echo "has purchaseIndexChart: " . (strpos($html, 'purchaseIndexChart') !== false ? 'yes' : 'no') . "\n";
echo "has Analisis de Compras: " . (strpos($html, 'Análisis de Compras') !== false ? 'yes' : 'no') . "\n";
// comprobar que una familia real aparece (no incompleta)
if ($fams && is_array($fams[0])) echo "primera familia: " . $fams[0]['familia'] . " / margen " . number_format($fams[0]['margin_rate'], 1) . "%\n";