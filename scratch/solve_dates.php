<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== SEARCHING DATE BOUNDARIES FOR COUNT 706 / SUM 343,233.17 (ALL STORES) ===\n";

// Get all pending vencimientos for ALL stores
$vencimientos = $db->select("
    SELECT 
        v.fecha_vencimiento,
        f.fecha_factura,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
    ORDER BY f.fecha_factura ASC
");

$n = count($vencimientos);
echo "Total pending vencimientos: $n\n";

// We want to find a range of indices [start, end] in the sorted array that gives:
// Count = 706
// Sum = 343233.17 +/- 1000.0
for ($i = 0; $i <= $n - 706; $i++) {
    $sum = 0.0;
    for ($j = $i; $j < $i + 706; $j++) {
        $sum += $vencimientos[$j]->pendiente;
    }
    if (abs($sum - 343233.17) < 2000.0) {
        $startDate = $vencimientos[$i]->fecha_factura;
        $endDate = $vencimientos[$i + 705]->fecha_factura;
        echo sprintf("Match on index %d to %d | Sum: %.2f | Date Range: %s to %s\n",
            $i, $i + 705, $sum, $startDate, $endDate
        );
    }
}
