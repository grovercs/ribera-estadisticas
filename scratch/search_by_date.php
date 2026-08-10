<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== SEARCHING WITH DATE FILTER AND MORE COMBINATIONS ===\n";

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

// Let's test different date limits (e.g. end of each month in 2025/2026, or specific dates)
$dateLimits = [
    null,
    '2026-06-27', // Today/Current
    '2026-06-30',
    '2026-05-31',
    '2026-04-30',
    '2026-03-31',
    '2026-02-28',
    '2026-01-31',
    '2025-12-31',
];

$stores = [
    'almacen_1' => fn($v) => $v->cod_almacen == 1,
    'almacen_2' => fn($v) => $v->cod_almacen == 2,
    'digit_1' => fn($v) => substr((string)$v->cod_factura, 2, 1) == '1',
    'digit_2' => fn($v) => substr((string)$v->cod_factura, 2, 1) == '2',
    'ALL' => fn($v) => true,
];

$fpFilters = [
    'all' => fn($v) => true,
    'excl_zjuz_zimp' => fn($v) => !in_array($v->cod_forma_liquidacion, ['ZJUZ', 'ZIMP']),
    'excl_z_fps' => fn($v) => substr($v->cod_forma_liquidacion, 0, 1) !== 'Z',
    'only_z_fps' => fn($v) => substr($v->cod_forma_liquidacion, 0, 1) === 'Z',
    'excl_z_and_imp_and_rc' => fn($v) => !in_array($v->cod_forma_liquidacion, ['ZJUZ', 'ZIMP', 'ZRC']) && substr($v->cod_forma_liquidacion, 0, 1) !== 'Z',
];

foreach ($dateLimits as $dateLimit) {
    foreach ($stores as $storeName => $storeFilter) {
        foreach ($fpFilters as $fpName => $fpFilter) {
            $filtered = array_filter($vencimientos, function($v) use ($storeFilter, $fpFilter, $dateLimit) {
                if (!$storeFilter($v)) return false;
                if (!$fpFilter($v)) return false;
                if ($dateLimit !== null && substr($v->fecha_vencimiento, 0, 10) > $dateLimit) return false;
                return true;
            });
            
            $count = count($filtered);
            $sum = array_sum(array_map(fn($v) => $v->pendiente, $filtered));
            
            // Check if count or sum is close to target (706, 343233.17)
            if (($count >= 690 && $count <= 720) || ($sum >= 330000 && $sum <= 350000)) {
                echo sprintf("Limit: %10s | Store: %10s | FP: %20s | Count: %4d | Sum: %12.2f\n",
                    $dateLimit ?? 'NONE', $storeName, $fpName, $count, $sum
                );
            }
        }
    }
}
