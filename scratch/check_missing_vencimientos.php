<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING INVOICES WITH PENDING BALANCE BUT NO VENCIMIENTO ===\n";

$res = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as cnt,
        SUM(f.importe - f.importe_cobrado) as sum_pend
    FROM facturas_ventas_cabecera f
    WHERE (f.importe - f.importe_cobrado) > 0
      AND NOT EXISTS (
          SELECT 1 FROM vencimientos_facturas v
          WHERE v.cod_factura = f.cod_factura
            AND v.tipo_factura = f.tipo_factura
            AND v.cod_empresa = f.cod_empresa
      )
    GROUP BY f.cod_almacen
");

foreach ($res as $r) {
    echo "Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
