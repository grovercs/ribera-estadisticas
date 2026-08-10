<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$res = $db->select("
    SELECT 
        v.cod_forma_liquidacion,
        SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) as store_char,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM devoluciones_vencimientos_ventas d
    INNER JOIN vencimientos_facturas v ON d.cod_factura_destino = v.cod_factura 
        AND d.tipo_factura_destino = v.tipo_factura 
        AND d.cod_empresa_destino = v.cod_empresa 
        AND d.numero_destino = v.numero
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY v.cod_forma_liquidacion, SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1)
");

echo "=== devoluciones_vencimientos_ventas + pending <> 0 FPs ===\n";
foreach ($res as $r) {
    echo "  Store: {$r->store_char} | FP: {$r->cod_forma_liquidacion} | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}
