<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== Grouping by cod_forma_liquidacion where pending > 0 ===\n";
$res1 = $db->select("
    SELECT 
        cod_forma_liquidacion, 
        emitido,
        COUNT(*) as cnt,
        SUM(importe) as total_imp,
        SUM(importe_cobrado) as total_cob,
        SUM(importe - importe_cobrado) as total_pend
    FROM vencimientos_facturas
    WHERE (importe - importe_cobrado) > 0
    GROUP BY cod_forma_liquidacion, emitido
    ORDER BY cod_forma_liquidacion, emitido
");
foreach ($res1 as $row) {
    echo sprintf("  FP: %-8s | Emitido: %s | Count: %4d | Pend: %12.2f\n", 
        $row->cod_forma_liquidacion, 
        $row->emitido, 
        $row->cnt, 
        $row->total_pend
    );
}
