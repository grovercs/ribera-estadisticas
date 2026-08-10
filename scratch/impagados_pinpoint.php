<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');
function show($erp, $label, $sql, $b = []) {
    $r = $erp->select($sql, $b)[0];
    printf("  %-60s count=%5d importe=%12.2f\n", $label, $r->c, $r->pend);
}
$alm = "EXISTS (SELECT 1 FROM facturas_ventas_cabecera f WHERE f.cod_factura=v.cod_factura AND f.tipo_factura=v.tipo_factura AND f.cod_empresa=v.cod_empresa AND f.cod_almacen IN (1,2))";
$noDev = "NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)";
$dev = "EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)";

echo "=== Buscando 22 / 11.416,01 ===\n";
show($erp, "devolucion + pend>0 + forma NOT LIKE Z",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion NOT LIKE 'Z%' AND $dev");
show($erp, "devolucion + pend>0 + forma LIKE Z",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion LIKE 'Z%' AND $dev");
show($erp, "forma IN (ZIMP,ZJUZ,ZPER,ZCYC) + importe_cobrado=0",
    "SELECT COUNT(*) c, SUM(v.importe) pend FROM vencimientos_facturas v WHERE v.importe_cobrado=0 AND v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')");
show($erp, "forma IN (ZIMP,ZJUZ,ZPER,ZCYC) total (sin filtro cobro)",
    "SELECT COUNT(*) c, SUM(v.importe) pend FROM vencimientos_facturas v WHERE v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')");
show($erp, "forma IN (ZIMP,ZJUZ,ZPER,ZCYC,ZRC,Z650) + importe_cobrado=0",
    "SELECT COUNT(*) c, SUM(v.importe) pend FROM vencimientos_facturas v WHERE v.importe_cobrado=0 AND v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC','ZRC','Z650')");
show($erp, "forma LIKE Z + importe_cobrado=0",
    "SELECT COUNT(*) c, SUM(v.importe) pend FROM vencimientos_facturas v WHERE v.importe_cobrado=0 AND v.cod_forma_liquidacion LIKE 'Z%'");

echo "\n=== Buscando 706 / 343.233,17 (pendientes ERP) ===\n";
show($erp, "pend>0 + noDev + cod_remesa IS NOT NULL + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm AND v.cod_remesa IS NOT NULL");
show($erp, "pend>0 + noDev + cod_remesa IS NOT NULL all",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND v.cod_remesa IS NOT NULL");
show($erp, "pend>0 + noDev + cod_banco IS NOT NULL + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm AND v.cod_banco IS NOT NULL");
show($erp, "pend>0 + noDev + forma NOT LIKE Z + cod_remesa NOT NULL all",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND v.cod_forma_liquidacion NOT LIKE 'Z%' AND v.cod_remesa IS NOT NULL");
show($erp, "pend>0 + noDev + cod_remesa IS NULL + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm AND v.cod_remesa IS NULL");

echo "\n=== Distribucion cod_remesa (pend>0, noDev, alm1+2) ===\n";
foreach ($erp->select("SELECT CASE WHEN v.cod_remesa IS NULL THEN 'NULL' ELSE 'TIENE' END g, COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm GROUP BY CASE WHEN v.cod_remesa IS NULL THEN 'NULL' ELSE 'TIENE' END") as $r)
    printf("  remesa=%s count=%d importe=%.2f\n", $r->g, $r->c, $r->pend);

echo "\n=== VENC/NOVEN split de pendientes (noDev, alm1+2, forma NOT LIKE Z) ===\n";
foreach ($erp->select("SELECT CASE WHEN v.fecha_vencimiento<GETDATE() THEN 'VENC' ELSE 'NOVEN' END g, COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $noDev AND $alm AND v.cod_forma_liquidacion NOT LIKE 'Z%' GROUP BY CASE WHEN v.fecha_vencimiento<GETDATE() THEN 'VENC' ELSE 'NOVEN' END") as $r)
    printf("  %s count=%d importe=%.2f\n", $r->g, $r->c, $r->pend);