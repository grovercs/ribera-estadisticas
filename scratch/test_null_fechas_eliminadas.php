<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// facturas_compras_cabecera con fecha_factura NULL: por fecha_contabilizacion / fecha_registro / fecha_hora_alta
echo "=== facturas_compras_cabecera con fecha_factura NULL ===\n";
foreach ($erp->select("SELECT COUNT(*) total, SUM(CASE WHEN fecha_contabilizacion IS NOT NULL THEN 1 ELSE 0 END) cont, SUM(CASE WHEN fecha_registro IS NOT NULL THEN 1 ELSE 0 END) reg, SUM(CASE WHEN fecha_hora_alta IS NOT NULL THEN 1 ELSE 0 END) alta FROM facturas_compras_cabecera WHERE fecha_factura IS NULL") as $r)
    printf("  total NULL fecha_factura=%d  con_fecha_cont=%d  con_fecha_reg=%d  con_fecha_alta=%d\n", $r->total, $r->cont, $r->reg, $r->alta);

// Por anyo segun fecha_contabilizacion (los que NO tienen fecha_factura)
echo "\n=== NULL fecha_factura por YEAR(fecha_contabilizacion) ===\n";
foreach ($erp->select("SELECT YEAR(fecha_contabilizacion) y, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE fecha_factura IS NULL AND fecha_contabilizacion IS NOT NULL GROUP BY YEAR(fecha_contabilizacion) ORDER BY y DESC") as $r)
    printf("  anyo=%s count=%4d sum=%12.2f\n", $r->y, $r->c, $r->s);

// sii_facturas_compras_eliminadas: columnas y aporte por anyo
echo "\n=== sii_facturas_compras_eliminadas columnas ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('sii_facturas_compras_eliminadas') ORDER BY column_id") as $r) echo "  $r->name\n";

// ¿Cruce con cabecera para ver importe de eliminadas?
echo "\n=== Eliminadas que existen en cabecera, por anyo (fecha_factura) ===\n";
try {
foreach ($erp->select("SELECT YEAR(c.fecha_factura) y, COUNT(*) c, SUM(c.importe_impuestos) s
    FROM sii_facturas_compras_eliminadas e
    JOIN facturas_compras_cabecera c ON c.cod_factura=e.cod_factura AND c.cod_empresa=e.cod_empresa AND c.cod_proveedor=e.cod_proveedor
    WHERE c.fecha_factura IS NOT NULL
    GROUP BY YEAR(c.fecha_factura) ORDER BY y DESC") as $r)
    printf("  anyo=%s count=%4d sum=%12.2f\n", $r->y, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// Test: excluir eliminadas del year_anterior 2025
echo "\n=== year_anterior 2025 EXCLUYENDO eliminadas ===\n";
$base = "FROM facturas_compras_cabecera c WHERE YEAR(c.fecha_factura)=2025";
$excl = " AND NOT EXISTS (SELECT 1 FROM sii_facturas_compras_eliminadas e WHERE e.cod_factura=c.cod_factura AND e.cod_empresa=c.cod_empresa AND e.cod_proveedor=c.cod_proveedor)";
$r1 = $erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s $base")[0];
$r2 = $erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s $base $excl")[0];
printf("  sin excluir:    count=%4d sum=%12.2f\n", $r1->c, $r1->s);
printf("  excluyendo:     count=%4d sum=%12.2f  (esperado 6572 / 6170109.55)\n", $r2->c, $r2->s);

// Cuantas cabecera hay con importe_impuestos NULL en 2025
echo "\n=== 2025: importe_impuestos NULL vs 0 vs negativo ===\n";
foreach ($erp->select("SELECT SUM(CASE WHEN importe_impuestos IS NULL THEN 1 ELSE 0 END) nul, SUM(CASE WHEN importe_impuestos=0 THEN 1 ELSE 0 END) zero, SUM(CASE WHEN importe_impuestos<0 THEN 1 ELSE 0 END) neg, SUM(CASE WHEN importe_impuestos>0 THEN 1 ELSE 0 END) pos FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2025") as $r)
    printf("  null=%d  zero=%d  neg=%d  pos=%d\n", $r->nul, $r->zero, $r->neg, $r->pos);