<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

function show($erp, $label, $sql, $b = []) {
    $r = $erp->select($sql, $b)[0];
    printf("  %-58s count=%5d importe=%12.2f\n", $label, $r->c, $r->pend);
}

$alm = "EXISTS (SELECT 1 FROM facturas_ventas_cabecera f WHERE f.cod_factura=v.cod_factura AND f.tipo_factura=v.tipo_factura AND f.cod_empresa=v.cod_empresa AND f.cod_almacen IN (1,2))";

echo "=== IMPAGADOS: candidatos (cual define el ERP?) ===\n";
show($erp, "A) devolucion + pendiente>0 (SQL actual) all almacenes",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM devoluciones_vencimientos_ventas d INNER JOIN vencimientos_facturas v ON d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero WHERE (v.importe-v.importe_cobrado)>0");
show($erp, "B) devolucion + pendiente>0 + almacen 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM devoluciones_vencimientos_ventas d INNER JOIN vencimientos_facturas v ON d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero WHERE (v.importe-v.importe_cobrado)>0 AND $alm");

echo "\n=== Forma liquidacion Z (impagados formas) ===\n";
show($erp, "C) forma IN (ZIMP,ZJUZ,ZPER,ZCYC) pendiente>0 all",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')");
show($erp, "D) forma IN (ZIMP,ZJUZ,ZPER,ZCYC) pendiente>0 + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND $alm");
show($erp, "E) forma IN (ZIMP,ZJUZ,ZPER,ZCYC,ZRC,Z650) pendiente>0 all",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC','ZRC','Z650')");
show($erp, "F) forma IN (ZIMP,ZJUZ,ZPER,ZCYC,ZRC,Z650) pendiente>0 + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC','ZRC','Z650') AND $alm");
show($erp, "G) forma LIKE 'Z%' pendiente>0 all",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion LIKE 'Z%'");
show($erp, "H) forma LIKE 'Z%' pendiente>0 + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion LIKE 'Z%' AND $alm");

echo "\n=== PENDIENTES: candidatos para 706 / 343.233,17 ===\n";
show($erp, "I) pendiente>0 all (no devolucion) excl Z formas",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion NOT LIKE 'Z%' AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)");
show($erp, "J) pendiente>0 + alm 1+2 excl Z formas",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion NOT LIKE 'Z%' AND $alm AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)");
show($erp, "K) VENC (fecha<getdate) pendiente>0 + alm 1+2 excl Z",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.fecha_vencimiento < GETDATE() AND v.cod_forma_liquidacion NOT LIKE 'Z%' AND $alm AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)");
show($erp, "L) VENC pendiente>0 + alm 1+2 (con Z)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.fecha_vencimiento < GETDATE() AND $alm AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)");
show($erp, "M) VENC pendiente>0 all (con Z)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.fecha_vencimiento < GETDATE() AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)");
show($erp, "N) pendiente>0 + alm 1+2 (SQL pnd actual)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND $alm AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)");

echo "\n=== Per-almacen impagados (SQL actual) ===\n";
foreach ($erp->select("SELECT f.cod_almacen, COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend
    FROM devoluciones_vencimientos_ventas d INNER JOIN vencimientos_facturas v ON d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE (v.importe-v.importe_cobrado)>0 GROUP BY f.cod_almacen") as $r)
    printf("  cod_almacen=%s count=%d importe=%.2f\n", $r->cod_almacen ?? 'NULL', $r->c, $r->pend);