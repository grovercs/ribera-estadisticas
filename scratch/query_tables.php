<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$results = $db->select("
    SELECT TOP 20 
        cod_factura,
        fecha_factura,
        fecha_vencimiento,
        fecha_devolucion,
        importe,
        importe_cobrado,
        (importe - importe_cobrado) as pendiente
    FROM vencimientos_facturas
    WHERE fecha_devolucion IS NOT NULL
");

print_r($results);

$allDevolved = $db->select("
    SELECT 
        COUNT(*) as cnt,
        SUM(importe) as sum_imp,
        SUM(importe_cobrado) as sum_cob,
        SUM(importe - importe_cobrado) as sum_pend
    FROM vencimientos_facturas
    WHERE fecha_devolucion IS NOT NULL
");
echo "\nTotal devoluciones:\n";
print_r($allDevolved);
