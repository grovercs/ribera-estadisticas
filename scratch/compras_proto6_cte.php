<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$join = "FROM hist_compras_linea l JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0";
$sql = "WITH ra AS (
        SELECT l.cod_articulo, MAX(l.descripcion) descripcion, SUM(CAST(l.cantidad AS float)) qty, SUM(CAST(l.importe AS float)) imp
        $join AND c.fecha_compra>=? AND c.fecha_compra<? GROUP BY l.cod_articulo
    ), rb AS (
        SELECT l.cod_articulo, SUM(CAST(l.cantidad AS float)) qty, SUM(CAST(l.importe AS float)) imp
        $join AND c.fecha_compra>=? AND c.fecha_compra<? GROUP BY l.cod_articulo
    )
    SELECT TOP 15 a.cod_articulo cod, MAX(a.descripcion) descripcion, MAX(fam.descripcion) familia,
        a.qty qtyA, b.qty qtyB, a.imp/NULLIF(a.qty,0) pA, b.imp/NULLIF(b.qty,0) pB
    FROM ra a JOIN rb b ON a.cod_articulo=b.cod_articulo
    LEFT JOIN articulos art ON a.cod_articulo=art.cod_articulo
    LEFT JOIN familias fam ON art.cod_familia=fam.cod_familia
    WHERE a.qty>? AND b.qty>?
    GROUP BY a.cod_articulo, a.descripcion, a.qty, b.qty, a.imp, b.imp
    ORDER BY (b.imp/NULLIF(b.qty,0))/NULLIF(a.imp/NULLIF(a.qty,0),0) DESC";
$p = ['20250101','20250701','20240101','20240701',20,20];
$t0=microtime(true);
$rows=$erp->select($sql,$p);
printf("UP: %d rows, %.2fs\n",count($rows),microtime(true)-$t0);
foreach($rows as $r) printf("  %-14s pA=%.3f pB=%.3f var=%+6.1f%% qtyA=%.0f %s\n",$r->cod,$r->pA,$r->pB,($r->pB/$r->pA-1)*100,$r->qtyA,substr(trim($r->descripcion),0,28));
$down=str_replace('ORDER BY (b.imp/NULLIF(b.qty,0))/NULLIF(a.imp/NULLIF(a.qty,0),0) DESC','ORDER BY (b.imp/NULLIF(b.qty,0))/NULLIF(a.imp/NULLIF(a.qty,0),0) ASC',$sql);
$drows=$erp->select($down,$p);
printf("DOWN: %d rows\n",count($drows));
foreach($drows as $r) printf("  %-14s pA=%.3f pB=%.3f var=%+6.1f%% qtyA=%.0f %s\n",$r->cod,$r->pA,$r->pB,($r->pB/$r->pA-1)*100,$r->qtyA,substr(trim($r->descripcion),0,28));