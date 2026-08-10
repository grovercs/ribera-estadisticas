<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING ALL Z FPs ===\n";

$res = $db->select("
    SELECT 
        cod_forma_liquidacion,
        CASE WHEN (importe - importe_cobrado) <> 0 THEN 1 ELSE 0 END as is_pending,
        COUNT(*) as cnt,
        SUM(importe - importe_cobrado) as sum_pend
    FROM vencimientos_facturas
    WHERE cod_forma_liquidacion LIKE 'Z%'
    GROUP BY cod_forma_liquidacion, CASE WHEN (importe - importe_cobrado) <> 0 THEN 1 ELSE 0 END
    ORDER BY cod_forma_liquidacion, is_pending
");

foreach ($res as $r) {
    echo "FP: {$r->cod_forma_liquidacion} | Is Pending: {$r->is_pending} | Count: {$r->cnt} | Sum: " . number_format($r->sum_pend, 2) . "\n";
}
