<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING TABLE vencimientos_facturas_compras ===\n";
try {
    $res = $db->select("
        SELECT TOP 5 * FROM vencimientos_facturas_compras
    ");
    print_r($res);
    
    // Group by status
    $res2 = $db->select("
        SELECT 
            COUNT(*) as cnt,
            SUM(importe - importe_pagado) as total_pend
        FROM vencimientos_facturas_compras
        WHERE (importe - importe_pagado) <> 0
    ");
    print_r($res2);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
