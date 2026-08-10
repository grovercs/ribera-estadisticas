<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$where = [
    "(p.importe - p.importe_pagado) <> 0",
    "p.fecha_vencimiento IS NOT NULL",
    "(p.cod_confirming IS NULL OR p.cod_confirming = '')",
    "p.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)",
    "p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE()))",
];
$q = "SELECT p.cod_proveedor, pr.razon_social, (p.importe - p.importe_pagado) as imp
      FROM vencimientos_facturas_compras p
      LEFT JOIN proveedores pr ON p.cod_proveedor = pr.cod_proveedor
      WHERE " . implode(' AND ', $where) . "
      ORDER BY p.fecha_vencimiento ASC";
$r = $erp->select($q);
$sum = 0;
foreach ($r as $x) $sum += (float)$x->imp;
printf("Detalle ALL: %d lineas, suma=%.2f (esperado 774345.54, diff %+.2f)\n", count($r), $sum, $sum - 774345.54);

echo "Top 8 proveedores por importe pendiente:\n";
$by = [];
foreach ($r as $x) { $n = trim($x->razon_social ?? '?'); if (!isset($by[$n])) $by[$n] = 0; $by[$n] += (float)$x->imp; }
arsort($by);
$i = 0;
foreach ($by as $n => $v) { if ($i++ >= 8) break; printf("  %-32s %12.2f\n", mb_substr($n, 0, 32), $v); }

// Por periodo
echo "\nDetalle por periodo (count / suma):\n";
foreach (['Mes Actual','Mes Siguiente','En 2 meses','En 3 meses'] as $per) {
    $w = $where;
    if ($per === 'Mes Actual') $w[] = "p.fecha_vencimiento <= EOMONTH(GETDATE())";
    elseif ($per === 'Mes Siguiente') $w[] = "p.fecha_vencimiento > EOMONTH(GETDATE()) AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,1,GETDATE()))";
    elseif ($per === 'En 2 meses') $w[] = "p.fecha_vencimiento > EOMONTH(DATEADD(MONTH,1,GETDATE())) AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,2,GETDATE()))";
    elseif ($per === 'En 3 meses') $w[] = "p.fecha_vencimiento > EOMONTH(DATEADD(MONTH,2,GETDATE())) AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE()))";
    $rr = $erp->select("SELECT (p.importe-p.importe_pagado) as imp FROM vencimientos_facturas_compras p WHERE " . implode(' AND ', $w));
    $s = 0; foreach ($rr as $x) $s += (float)$x->imp;
    printf("  %-15s count=%4d  suma=%12.2f\n", $per, count($rr), $s);
}