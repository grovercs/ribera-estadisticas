<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING TIPO FACTURA IN PENDING ===\n";

$res = $db->select("
    SELECT 
        v.tipo_factura,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as sum_pend
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY v.tipo_factura
");

foreach ($res as $r) {
    echo "Tipo Factura: {$r->tipo_factura} | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
