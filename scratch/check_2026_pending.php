<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== 2026 PENDING INVOICES BY FACTURA YEAR ===\n";

$res1 = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as sum_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
      AND YEAR(f.fecha_factura) = 2026
    GROUP BY f.cod_almacen
");

foreach ($res1 as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}

echo "\n=== 2026 PENDING INVOICES BY VENCIMIENTO YEAR ===\n";

$res2 = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as sum_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
      AND YEAR(v.fecha_vencimiento) = 2026
    GROUP BY f.cod_almacen
");

foreach ($res2 as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
