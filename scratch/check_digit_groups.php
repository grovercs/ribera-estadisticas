<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== GROUP BY 3RD DIGIT OF COD_FACTURA ===\n";

$res = $db->select("
    SELECT 
        SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) as store_digit,
        v.emitido,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as sum_pend
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1), v.emitido
");

foreach ($res as $r) {
    echo "Digit: {$r->store_digit} | Emitido: " . ($r->emitido ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
