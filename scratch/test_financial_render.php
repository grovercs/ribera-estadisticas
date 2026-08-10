<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$req = Request::create('/financial', 'GET');
$view = $ctrl->index($req);
$html = $view->render();
echo "render OK, len=" . strlen($html) . "\n";
echo "has purchaseIndexChart: " . (strpos($html, 'purchaseIndexChart') !== false ? 'yes' : 'no') . "\n";
echo "has Analisis de Compras: " . (strpos($html, 'Análisis de Compras') !== false ? 'yes' : 'no') . "\n";
echo "has subida: " . (strpos($html, 'Mayor subida') !== false ? 'yes' : 'no') . "\n";
echo "has bajada: " . (strpos($html, 'Mayor bajada') !== false ? 'yes' : 'no') . "\n";
echo "has Indice de precio: " . (strpos($html, 'Índice de precio de compra') !== false ? 'yes' : 'no') . "\n";