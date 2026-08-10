<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== 2025 empresa=1: COUNT(*) vs DISTINCT ===\n";
$r = $erp->select("SELECT COUNT(*) as rows,
    COUNT(DISTINCT cod_factura) as dist_fac,
    COUNT(DISTINCT cod_factura+'|'+CAST(cod_empresa as varchar)) as dist_fac_emp,
    COUNT(DISTINCT cod_factura+'|'+CAST(cod_empresa as varchar)+'|'+CAST(cod_proveedor as varchar)) as dist_fac_emp_prov
    FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025")[0];
printf("  rows=%d  distinct(cod_factura)=%d  distinct(fac+emp)=%d  distinct(fac+emp+prov)=%d\n",
    $r->rows, $r->dist_fac, $r->dist_fac_emp, $r->dist_fac_emp_prov);

echo "\n=== RENFE 18189 2025-04-11: filas vs unicas (con rowguid) ===\n";
$rows = $erp->select("SELECT cod_factura, cod_empresa, cod_proveedor, importe, importe_impuestos, rowguid
    FROM facturas_compras_cabecera
    WHERE cod_proveedor=18189 AND CONVERT(date,fecha_factura)='2025-04-11'
    ORDER BY cod_factura, rowguid");
printf("  total filas: %d\n", count($rows));
$seen = [];
foreach ($rows as $r) {
    $k = $r->cod_factura.'|'.$r->rowguid;
    $dup = isset($seen[$r->cod_factura]) ? '  <-- DUP cod_factura' : '';
    $seen[$r->cod_factura] = true;
    printf("  fac=%s emp=%s prov=%s imp=%.2f impIV=%.2f rg=%s%s\n", $r->cod_factura, $r->cod_empresa, $r->cod_proveedor, $r->importe, $r->importe_impuestos, substr($r->rowguid,0,8), $dup);
}

echo "\n=== Cuantas facturas (fac+emp) tienen >1 fila en 2025 empresa=1 ===\n";
$dups = $erp->select("SELECT cod_factura, cod_proveedor, COUNT(*) c, SUM(importe_impuestos) s
    FROM facturas_compras_cabecera
    WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025
    GROUP BY cod_factura, cod_proveedor
    HAVING COUNT(*) > 1
    ORDER BY c DESC");
$totExtra = 0; $totDupImport = 0;
foreach ($dups as $d) { $totExtra += ($d->c - 1); $totDupImport += $d->s; }
printf("  facturas duplicadas: %d, filas extra (suma de c-1): %d, SUM(importe_impuestos) de los dup: %.2f\n",
    count($dups), $totExtra, $totDupImport);

echo "\n=== 2025 empresa=1 con DISTINCT: importe ===\n";
$r = $erp->select("SELECT COUNT(DISTINCT cod_factura+'|'+CAST(cod_empresa as varchar)) c, SUM(importe_impuestos) s FROM (SELECT cod_factura, cod_empresa, importe_impuestos, ROW_NUMBER() OVER(PARTITION BY cod_factura, cod_empresa ORDER BY rowguid) rn FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025) x WHERE rn=1")[0];
printf("  sin duplicados (rn=1): count=%d sum=%.2f  (esp 6572 / 6170109.55)\n", $r->c, $r->s);