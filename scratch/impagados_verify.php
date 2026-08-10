<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');
function show($erp, $label, $sql, $b = []) {
    $r = $erp->select($sql, $b)[0];
    printf("  %-62s count=%5d importe=%12.2f\n", $label, $r->c, $r->pend);
}
$alm = "EXISTS (SELECT 1 FROM facturas_ventas_cabecera f WHERE f.cod_factura=v.cod_factura AND f.tipo_factura=v.tipo_factura AND f.cod_empresa=f.cod_empresa AND f.cod_almacen IN (1,2))";
$alm2 = "EXISTS (SELECT 1 FROM facturas_ventas_cabecera f WHERE f.cod_factura=v.cod_factura AND f.tipo_factura=f.tipo_factura AND f.cod_empresa=v.cod_empresa AND f.cod_almacen IN (1,2))";
$noDev = "NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)";
$Z4 = "v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')";

echo "=== IMPAGADOS = forma IN (ZIMP,ZJUZ,ZPER,ZCYC) — verificar importe 11.416,01 ===\n";
show($erp, "count total + SUM(importe-importe_cobrado) (sin filtro pend)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE $Z4");
show($erp, "count total + SUM(importe) donde cobrado=0",
    "SELECT COUNT(*) c, SUM(v.importe) pend FROM vencimientos_facturas v WHERE $Z4 AND v.importe_cobrado=0");
show($erp, "pend>0 + SUM(importe-importe_cobrado)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE $Z4 AND (v.importe-v.importe_cobrado)>0");
show($erp, "count + SUM(importe-importe_cobrado) + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE $Z4 AND $alm2");

echo "\n=== Desglose por forma Z (pend>0) ===\n";
foreach ($erp->select("SELECT v.cod_forma_liquidacion f, COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE $Z4 GROUP BY v.cod_forma_liquidacion ORDER BY v.cod_forma_liquidacion") as $r)
    printf("  %s count=%d pend=%.2f\n", $r->f, $r->c, $r->pend);

echo "\n=== PENDIENTES 706 / 343.233,17 — grid de candidatos ===\n";
show($erp, "noDev + forma NOT IN Z4 + cod_remesa IS NULL + alm1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm2 AND v.cod_forma_liquidacion NOT IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND v.cod_remesa IS NULL");
show($erp, "noDev + forma NOT LIKE Z + cod_remesa IS NULL + alm1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm2 AND v.cod_forma_liquidacion NOT LIKE 'Z%' AND v.cod_remesa IS NULL");
show($erp, "noDev + cod_remesa IS NULL + alm1+2 (incl Z)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm2 AND v.cod_remesa IS NULL");
show($erp, "noDev + forma NOT IN Z4 + alm1+2 (sin filtro remesa)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm2 AND v.cod_forma_liquidacion NOT IN ('ZIMP','ZJUZ','ZPER','ZCYC')");
show($erp, "noDev + forma NOT IN Z4 + VENC + alm1+2 + remesa NULL",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm2 AND v.cod_forma_liquidacion NOT IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND v.cod_remesa IS NULL AND v.fecha_vencimiento < GETDATE()");
show($erp, "noDev + forma NOT IN Z4 + VENC + alm1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm2 AND v.cod_forma_liquidacion NOT IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND v.fecha_vencimiento < GETDATE()");