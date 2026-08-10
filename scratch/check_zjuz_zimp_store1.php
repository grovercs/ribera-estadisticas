<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING ZJUZ, ZIMP, ZPER FOR STORE 1 ===\n";

$res = $db->select("
    SELECT 
        v.cod_forma_liquidacion,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as sum_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
      AND f.cod_almacen = 1
      AND v.cod_forma_liquidacion IN ('ZJUZ', 'ZIMP', 'ZPER')
    GROUP BY v.cod_forma_liquidacion
");

foreach ($res as $r) {
    echo "FP: {$r->cod_forma_liquidacion} | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
