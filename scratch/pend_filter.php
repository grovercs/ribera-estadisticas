<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');
function show($erp, $label, $sql, $b = []) {
    $r = $erp->select($sql, $b)[0];
    printf("  %-58s count=%5d pend=%12.2f\n", $label, $r->c, $r->pend);
}
$alm = "EXISTS (SELECT 1 FROM facturas_ventas_cabecera f WHERE f.cod_factura=v.cod_factura AND f.tipo_factura=v.tipo_factura AND f.cod_empresa=v.cod_empresa AND f.cod_almacen IN (1,2))";
$Z4 = "v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')";

echo "=== Filtro por cod_remesa IS NULL (perfil dice que todos los matched lo son) ===\n";
show($erp, "pend>0 + remesa NULL (sin mas)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_remesa IS NULL");
show($erp, "pend>0 + remesa NULL + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_remesa IS NULL AND $alm");
show($erp, "pend>0 + remesa NULL + NOT Z4 (alm 1+2)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_remesa IS NULL AND NOT ($Z4) AND $alm");
show($erp, "pend>0 + remesa NULL + NOT Z4 (sin filtro alm)",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_remesa IS NULL AND NOT ($Z4)");
show($erp, "remesa NULL (sin filtro pend) + NOT Z4 + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE v.cod_remesa IS NULL AND NOT ($Z4) AND $alm");
show($erp, "remesa NULL (sin filtro pend) + alm 1+2",
    "SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend FROM vencimientos_facturas v WHERE v.cod_remesa IS NULL AND $alm");

echo "\n=== Que son las formas Z? Z650/ZRC ¿impagados o pendientes? ===\n";
foreach ($erp->select("SELECT v.cod_forma_liquidacion f, COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend
    FROM vencimientos_facturas v WHERE (v.importe-v.importe_cobrado)>0 AND v.cod_forma_liquidacion LIKE 'Z%'
    GROUP BY v.cod_forma_liquidacion ORDER BY c DESC") as $r)
    printf("  %-8s count=%4d pend=%11.2f\n", $r->f, $r->c, $r->pend);

echo "\n=== CSV: filas donde importe(col5) != pendiente(col6) ===\n";
$raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(__DIR__ . '/pend_all.csv'));
$lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');
$diff = 0; $totImp=0; $totPend=0;
foreach ($lines as $lin) {
    $p = array_map(fn($x)=>trim($x), explode(';', $lin));
    if (count($p)<7 || $p[1]==='' || $p[4]==='TOTAL :') continue;
    $imp=(float)str_replace(',','.',$p[5]); $pend=(float)str_replace(',','.',$p[6]);
    $totImp+=$imp; $totPend+=$pend;
    if (abs($imp-$pend)>0.005) { $diff++; if ($diff<=10) printf("  fac=%s imp=%.2f pend=%.2f\n",$p[1],$imp,$pend); }
}
printf("  Total filas con imp!=pend: %d   SUM imp=%.2f SUM pend=%.2f\n",$diff,$totImp,$totPend);