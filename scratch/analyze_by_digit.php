<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== VENCIMIENTOS WITH NO CABECERA ===\n";
$resNoCab = $db->select("
    SELECT COUNT(*) as cnt, SUM(importe - importe_cobrado) as total
    FROM vencimientos_facturas v
    WHERE (importe - importe_cobrado) <> 0
      AND NOT EXISTS (
          SELECT 1 FROM facturas_ventas_cabecera f 
          WHERE f.cod_factura = v.cod_factura 
            AND f.tipo_factura = v.tipo_factura 
            AND f.cod_empresa = v.cod_empresa
      )
");
echo "Count: " . $resNoCab[0]->cnt . " | Sum: " . $resNoCab[0]->total . "\n";

// Let's analyze all pending vencimientos and determine store from the 3rd digit of cod_factura
$res3rdDigit = $db->select("
    SELECT 
        SUBSTRING(CAST(cod_factura AS VARCHAR), 3, 1) as store_digit,
        COUNT(*) as cnt,
        SUM(importe - importe_cobrado) as total
    FROM vencimientos_facturas v
    WHERE (importe - importe_cobrado) <> 0
    GROUP BY SUBSTRING(CAST(cod_factura AS VARCHAR), 3, 1)
");
echo "\n=== GROUPING BY 3RD DIGIT OF COD_FACTURA ===\n";
foreach ($res3rdDigit as $r) {
    echo "  Digit: {$r->store_digit} | Count: {$r->cnt} | Sum: " . number_format($r->total, 2) . "\n";
}
