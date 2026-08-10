<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$erp = DB::connection('erp');

echo "=== RESUMEN POR PERIODO (nueva consulta) ===\n";
$res = $erp->select("
    SELECT
        CASE
            WHEN p.fecha_vencimiento < GETDATE() THEN 'Vencidos'
            WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
            ELSE 'Mas de 3 meses'
        END as periodo,
        SUM(p.importe - p.importe_pagado) as importe
    FROM vencimientos_facturas_compras p
    WHERE (p.importe - p.importe_pagado) > 0
        AND p.fecha_vencimiento IS NOT NULL
    GROUP BY
        CASE
            WHEN p.fecha_vencimiento < GETDATE() THEN 'Vencidos'
            WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
            ELSE 'Mas de 3 meses'
        END
");
$total = 0;
foreach ($res as $r) {
    printf("  %-15s %12.2f €\n", $r->periodo, $r->importe);
    $total += $r->importe;
}
printf("  %-15s %12.2f € (TOTAL)\n", '', $total);

echo "\n=== DETALLE 'Vencidos' (TOP 5) ===\n";
$det = $erp->select("
    SELECT
        p.cod_proveedor, pr.razon_social, pr.cif,
        p.cod_factura, p.fecha_vencimiento,
        (p.importe - p.importe_pagado) as importe
    FROM vencimientos_facturas_compras p
    LEFT JOIN proveedores pr ON p.cod_proveedor = pr.cod_proveedor
    WHERE (p.importe - p.importe_pagado) > 0
      AND p.fecha_vencimiento IS NOT NULL
      AND p.fecha_vencimiento < GETDATE()
    ORDER BY p.fecha_vencimiento ASC
    OFFSET 0 ROWS FETCH NEXT 5 ROWS ONLY
");
foreach ($det as $r) {
    printf("  %s | %-25s | %s | vto %s | %.2f €\n",
        $r->cod_factura, substr(trim($r->razon_social ?? '?'),0,25),
        trim($r->cif ?? '-'), substr($r->fecha_vencimiento,0,10), $r->importe);
}
echo "\nTotal registros vencidos: ";
$cnt = $erp->select("SELECT COUNT(*) AS n FROM vencimientos_facturas_compras WHERE (importe - importe_pagado) > 0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < GETDATE()");
echo $cnt[0]->n . "\n";