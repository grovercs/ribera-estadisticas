<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== LARGEST PENDING INVOICES IN STORE 2 ===\n";

$res = $db->select("
    SELECT TOP 30
        v.cod_factura,
        v.cod_forma_liquidacion,
        f.fecha_factura,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
      AND f.cod_almacen = 2
    ORDER BY pendiente DESC
");

foreach ($res as $r) {
    echo "Inv: {$r->cod_factura} | Date: {$r->fecha_factura} | FP: {$r->cod_forma_liquidacion} | Pendiente: " . number_format($r->pendiente, 2) . "\n";
}
