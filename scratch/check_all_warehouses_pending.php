<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== ALL WAREHOUSES PENDING ===\n";

$res = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as sum_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY f.cod_almacen
    ORDER BY f.cod_almacen
");

foreach ($res as $r) {
    echo "Almacen: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
