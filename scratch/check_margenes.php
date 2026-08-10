<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');
$year = 2026;

echo "=== Margenes with tipo_venta = 1 (No other line filters) ===\n";
$resRaw = $db->select("
    SELECT
        v.cod_almacen,
        SUM(l.importe_impuestos) as venta,
        SUM(l.precio_coste * l.cantidad) as coste,
        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as margen
    FROM hist_ventas_cabecera v
    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
    WHERE YEAR(v.fecha_venta) = ?
        AND v.tipo_venta = 1
        AND ISNULL(v.anulada, '') <> 'S'
    GROUP BY v.cod_almacen
", [$year]);
print_r($resRaw);
