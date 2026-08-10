<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING hist_vencimientos_facturas ===\n";

$res = $db->select("
    SELECT COUNT(*) as cnt
    FROM hist_vencimientos_facturas
");
echo "Total rows: {$res[0]->cnt}\n";

$res2 = $db->select("
    SELECT COUNT(*) as cnt, SUM(importe - importe_cobrado) as sum_pend
    FROM hist_vencimientos_facturas
    WHERE (importe - importe_cobrado) <> 0
");
echo "Pending rows count: {$res2[0]->cnt} | Sum: " . number_format($res2[0]->sum_pend, 2) . "\n";
