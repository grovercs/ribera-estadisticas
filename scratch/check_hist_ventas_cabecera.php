<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING hist_ventas_cabecera PENDING ===\n";

$res = $db->select("
    SELECT 
        cod_almacen,
        COUNT(*) as cnt,
        SUM(importe_pendiente) as sum_pend
    FROM hist_ventas_cabecera
    WHERE importe_pendiente > 0
      AND ISNULL(anulada, '') <> 'S'
    GROUP BY cod_almacen
");
foreach ($res as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}

echo "\n=== GROUPED BY YEAR ===\n";
$res2 = $db->select("
    SELECT 
        cod_almacen,
        YEAR(fecha_venta) as anio,
        COUNT(*) as cnt,
        SUM(importe_pendiente) as sum_pend
    FROM hist_ventas_cabecera
    WHERE importe_pendiente > 0
      AND ISNULL(anulada, '') <> 'S'
    GROUP BY cod_almacen, YEAR(fecha_venta)
    ORDER BY cod_almacen, anio
");
foreach ($res2 as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Year: {$r->anio} | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
