<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING OVERDUE PENDING INVOICES (fecha_vencimiento <= today) ===\n";

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
      AND v.fecha_vencimiento <= GETDATE()
    GROUP BY f.cod_almacen
");

foreach ($res as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}

echo "\n=== AND WITH DEVOLUCIONES EXCLUDED ===\n";

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
      AND v.fecha_vencimiento <= GETDATE()
      AND NOT EXISTS (
          SELECT 1 FROM devoluciones_vencimientos_ventas d
          WHERE d.cod_factura_destino = v.cod_factura
            AND d.tipo_factura_destino = v.tipo_factura
            AND d.cod_empresa_destino = v.cod_empresa
            AND d.numero_destino = v.numero
      )
    GROUP BY f.cod_almacen
");

foreach ($res2 as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
