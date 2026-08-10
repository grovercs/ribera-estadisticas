<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== SEARCHING FOR PENDIENTES (706, 343,233.17 €) ===\n";

// Let's run a query to get all pending vencimientos with their store and FP.
$vencimientos = $db->select("
    SELECT 
        v.cod_factura,
        v.cod_forma_liquidacion,
        v.emitido,
        v.fecha_vencimiento,
        f.cod_almacen,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
");

echo "Total pending vencimientos in DB: " . count($vencimientos) . "\n";

// Let's test different criteria for Store 1 (Pont de Suert) and Store 2 (Vielha).
// We'll test grouping by:
// 1. f.cod_almacen
// 2. 3rd digit of cod_factura
// And different FP filters:
// - All FPs
// - Excluding ZJUZ/ZIMP
// - Excluding all Z-FPs
// - Only regular FPs (not starting with Z)

$stores = [
    'almacen_1' => fn($v) => $v->cod_almacen == 1,
    'almacen_2' => fn($v) => $v->cod_almacen == 2,
    'digit_1' => fn($v) => substr((string)$v->cod_factura, 2, 1) == '1',
    'digit_2' => fn($v) => substr((string)$v->cod_factura, 2, 1) == '2',
];

$fpFilters = [
    'all' => fn($v) => true,
    'excl_zjuz_zimp' => fn($v) => !in_array($v->cod_forma_liquidacion, ['ZJUZ', 'ZIMP']),
    'excl_z_fps' => fn($v) => substr($v->cod_forma_liquidacion, 0, 1) !== 'Z',
    'only_z_fps' => fn($v) => substr($v->cod_forma_liquidacion, 0, 1) === 'Z',
];

foreach ($stores as $storeName => $storeFilter) {
    foreach ($fpFilters as $fpName => $fpFilter) {
        $filtered = array_filter($vencimientos, fn($v) => $storeFilter($v) && $fpFilter($v));
        $count = count($filtered);
        $sum = array_sum(array_map(fn($v) => $v->pendiente, $filtered));
        
        echo sprintf("  Store: %10s | FP: %15s | Count: %4d | Sum: %12.2f\n",
            $storeName, $fpName, $count, $sum
        );
    }
}

// Let's also check if there is some combination of stores (e.g. all stores combined)
foreach ($fpFilters as $fpName => $fpFilter) {
    $filtered = array_filter($vencimientos, fn($v) => $fpFilter($v));
    $count = count($filtered);
    $sum = array_sum(array_map(fn($v) => $v->pendiente, $filtered));
    
    echo sprintf("  Store: %10s | FP: %15s | Count: %4d | Sum: %12.2f\n",
        'ALL', $fpName, $count, $sum
    );
}
