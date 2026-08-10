<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$b = [':startA'=>'20250101', ':endA'=>'20250701', ':startB'=>'20240101', ':endB'=>'20240701', ':qtyMin'=>20];
$sql = "SELECT TOP 5 l.cod_articulo, MAX(l.descripcion) descripcion,
    SUM(CASE WHEN c.fecha_compra>=:startA AND c.fecha_compra<:endA THEN CAST(l.cantidad AS float) ELSE 0 END) qtyA,
    SUM(CASE WHEN c.fecha_compra>=:startB AND c.fecha_compra<:endB THEN CAST(l.cantidad AS float) ELSE 0 END) qtyB,
    SUM(CASE WHEN c.fecha_compra>=:startA AND c.fecha_compra<:endA THEN CAST(l.importe AS float) ELSE 0 END)/NULLIF(SUM(CASE WHEN c.fecha_compra>=:startA AND c.fecha_compra<:endA THEN CAST(l.cantidad AS float) ELSE 0 END),0) pA,
    SUM(CASE WHEN c.fecha_compra>=:startB AND c.fecha_compra<:endB THEN CAST(l.importe AS float) ELSE 0 END)/NULLIF(SUM(CASE WHEN c.fecha_compra>=:startB AND c.fecha_compra<:endB THEN CAST(l.cantidad AS float) ELSE 0 END),0) pB
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0
      AND ((c.fecha_compra>=:startA AND c.fecha_compra<:endA) OR (c.fecha_compra>=:startB AND c.fecha_compra<:endB))
    GROUP BY l.cod_articulo
    HAVING SUM(CASE WHEN c.fecha_compra>=:startA AND c.fecha_compra<:endA THEN CAST(l.cantidad AS float) ELSE 0 END) > :qtyMin
       AND SUM(CASE WHEN c.fecha_compra>=:startB AND c.fecha_compra<:endB THEN CAST(l.cantidad AS float) ELSE 0 END) > :qtyMin
    ORDER BY (pB/NULLIF(pA,0)) DESC";
$t0=microtime(true);
try {
    $rows = $erp->select($sql, $b);
    echo "OK named-param reuse: ".count($rows)." rows, ".round(microtime(true)-$t0,2)."s\n";
    foreach ($rows as $r) printf("  %-14s pA=%.3f pB=%.3f var=%+6.1f%%  %s\n", $r->cod_articulo, $r->pA, $r->pB, ($r->pB/$r->pA-1)*100, substr(trim($r->descripcion),0,28));
} catch (\Throwable $e) { echo "FAIL: ".$e->getMessage()."\n"; }