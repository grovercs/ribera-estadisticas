<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);

cache()->flush();

$routes = [
    'familias' => '/financial/detalle-familias',
    'productos' => '/financial/detalle-productos',
    'clientes' => '/financial/detalle-clientes',
    'ppv' => '/financial/detalle-ppv',
];
$params = ['year_from' => '2026', 'year_to' => '2026'];

$dispatch = [
    'familias' => 'detalleFamilias',
    'productos' => 'detalleProductos',
    'clientes' => 'detalleClientes',
    'ppv' => 'detallePpv',
];
foreach ($routes as $name => $path) {
    $t = microtime(true);
    try {
        $method = $dispatch[$name];
        $view = $ctrl->$method(Request::create($path, 'GET', $params));
        $html = $view->render();
        $data = $view->getData();
        $rows = $data['rows'] ?? [];
        $first = $rows[0] ?? null;
        printf("[%-9s] total=%d totalPages=%d rows=%d len=%d %.2fs\n",
            $name, $data['total'] ?? 0, $data['totalPages'] ?? 0, count($rows), strlen($html), microtime(true)-$t);
        if ($first) {
            $keys = array_keys($first);
            echo "  cols: " . implode(',', array_slice($keys, 0, 6)) . "\n";
            echo "  first: " . json_encode(array_slice($first, 0, 3, true), JSON_UNESCAPED_UNICODE) . "\n";
        }
    } catch (\Throwable $e) {
        echo "[%-9s] ERROR: " . $e->getMessage() . "\n  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}