<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== DENSE SEARCH FOR PENDIENTES ===\n";

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

// Let's generate a list of dates (end of months from 2012 to 2026, and weekly for 2026)
$dates = [null];
for ($y = 2025; $y <= 2026; $y++) {
    for ($m = 1; $m <= 12; $m++) {
        $dates[] = sprintf("%04d-%02d-%02d", $y, $m, cal_days_in_month(CAL_GREGORIAN, $m, $y));
    }
}
// Add some specific dates around June/July 2026
$dates[] = '2026-06-15';
$dates[] = '2026-06-20';
$dates[] = '2026-06-25';
$dates[] = '2026-06-27';
$dates[] = '2026-07-05';
$dates[] = '2026-07-10';
$dates[] = '2026-07-15';
$dates[] = '2026-07-20';
$dates[] = '2026-07-25';

$dates = array_unique($dates);
sort($dates);

$stores = [
    'alm_1' => fn($v) => $v->cod_almacen == 1,
    'alm_2' => fn($v) => $v->cod_almacen == 2,
    'dig_1' => fn($v) => substr((string)$v->cod_factura, 2, 1) == '1',
    'dig_2' => fn($v) => substr((string)$v->cod_factura, 2, 1) == '2',
];

$fpFilters = [
    'all' => fn($v) => true,
    'excl_z' => fn($v) => substr($v->cod_forma_liquidacion, 0, 1) !== 'Z',
    'excl_zjuz_zimp' => fn($v) => !in_array($v->cod_forma_liquidacion, ['ZJUZ', 'ZIMP']),
];

foreach ($dates as $d) {
    foreach ($stores as $sName => $sFilter) {
        foreach ($fpFilters as $fpName => $fpFilter) {
            $filtered = array_filter($vencimientos, function($v) use ($sFilter, $fpFilter, $d) {
                if (!$sFilter($v)) return false;
                if (!$fpFilter($v)) return false;
                if ($d !== null && substr($v->fecha_vencimiento, 0, 10) > $d) return false;
                return true;
            });
            
            $count = count($filtered);
            $sum = array_sum(array_map(fn($v) => $v->pendiente, $filtered));
            
            // Check if sum is close to 343,233.17 (within 5000) or count is close to 706 (within 10)
            if (abs($sum - 343233.17) < 10000 || abs($count - 706) < 15) {
                echo sprintf("Date: %10s | Store: %5s | FP: %14s | Count: %4d | Sum: %12.2f\n",
                    $d ?? 'NONE', $sName, $fpName, $count, $sum
                );
            }
        }
    }
}
