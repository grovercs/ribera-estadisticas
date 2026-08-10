<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Top familias por volumen de compra (2024-2026)
echo "=== Top 8 familias por importe de compra ===\n";
$topFam = $erp->select("SELECT TOP 8 a.cod_familia, MAX(fam.descripcion) descripcion,
    SUM(CAST(l.importe AS float)) importe, SUM(CAST(l.cantidad AS float)) cantidad
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    JOIN articulos a ON l.cod_articulo = a.cod_articulo
    JOIN familias fam ON a.cod_familia = fam.cod_familia
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0 AND c.fecha_compra>='20240101' AND c.fecha_compra<'20270101'
    GROUP BY a.cod_familia ORDER BY importe DESC");
$famIds = [];
foreach ($topFam as $r) { printf("  %-6s %-30s importe=%11.2f qty=%.0f\n", $r->cod_familia, substr(trim($r->descripcion),0,30), $r->importe, $r->cantidad); $famIds[] = $r->cod_familia; }

// Indice mensual por familia (weighted avg = SUM(importe)/SUM(cantidad))
echo "\n=== Indice precio de compra mensual por familia (avg neto por unidad) ===\n";
$inList = "'" . implode("','", $famIds) . "'";
$idx = $erp->select("SELECT a.cod_familia, MAX(fam.descripcion) descripcion,
    YEAR(c.fecha_compra) y, MONTH(c.fecha_compra) m,
    SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) avg_precio
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    JOIN articulos a ON l.cod_articulo = a.cod_articulo
    JOIN familias fam ON a.cod_familia = fam.cod_familia
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0 AND c.fecha_compra>='20240101' AND c.fecha_compra<'20270101'
      AND a.cod_familia IN ($inList)
    GROUP BY a.cod_familia, YEAR(c.fecha_compra), MONTH(c.fecha_compra)
    ORDER BY a.cod_familia, y, m");
// Indexar cada familia a su primer mes = 100
$base = [];
foreach ($idx as $r) { if (!isset($base[$r->cod_familia])) $base[$r->cod_familia] = $r->avg_precio; }
$shown = 0;
foreach ($idx as $r) {
    $b = $base[$r->cod_familia] ?: 1;
    printf("  %-6s %d-%02d avg=%.4f indice=%.1f\n", $r->cod_familia, $r->y, $r->m, $r->avg_precio, ($r->avg_precio/$b)*100);
}

// PPV: articulos con mayor variacion de precio (2025 vs 2024, mismos meses 1..6)
echo "\n=== PPV: top 12 subidas de precio de compra 2025 vs 2024 (meses 1-6, qty>20 en ambos) ===\n";
$t0=microtime(true);
$ppv = $erp->select("SELECT l.cod_articulo, MAX(l.descripcion) descripcion, MAX(fam.descripcion) familia,
    SUM(CASE WHEN c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' THEN CAST(l.cantidad AS float) ELSE 0 END) qty24,
    SUM(CASE WHEN c.fecha_compra>='20250101' AND c.fecha_compra<'20250701' THEN CAST(l.cantidad AS float) ELSE 0 END) qty25,
    SUM(CASE WHEN c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' THEN CAST(l.importe AS float) ELSE 0 END)/NULLIF(SUM(CASE WHEN c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' THEN CAST(l.cantidad AS float) ELSE 0 END),0) p24,
    SUM(CASE WHEN c.fecha_compra>='20250101' AND c.fecha_compra<'20250701' THEN CAST(l.importe AS float) ELSE 0 END)/NULLIF(SUM(CASE WHEN c.fecha_compra>='20250101' AND c.fecha_compra<'20250701' THEN CAST(l.cantidad AS float) ELSE 0 END),0) p25
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    LEFT JOIN articulos a ON l.cod_articulo=a.cod_articulo
    LEFT JOIN familias fam ON a.cod_familia=fam.cod_familia
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0
      AND (c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' OR c.fecha_compra>='20250101' AND c.fecha_compra<'20250701')
    GROUP BY l.cod_articulo
    HAVING SUM(CASE WHEN c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' THEN CAST(l.cantidad AS float) ELSE 0 END) > 20
       AND SUM(CASE WHEN c.fecha_compra>='20250101' AND c.fecha_compra<'20250701' THEN CAST(l.cantidad AS float) ELSE 0 END) > 20
    ORDER BY (SUM(CASE WHEN c.fecha_compra>='20250101' AND c.fecha_compra<'20250701' THEN CAST(l.importe AS float) ELSE 0 END)/NULLIF(SUM(CASE WHEN c.fecha_compra>='20250101' AND c.fecha_compra<'20250701' THEN CAST(l.cantidad AS float) ELSE 0 END),0))
           / NULLIF(SUM(CASE WHEN c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' THEN CAST(l.importe AS float) ELSE 0 END)/NULLIF(SUM(CASE WHEN c.fecha_compra>='20240101' AND c.fecha_compra<'20240701' THEN CAST(l.cantidad AS float) ELSE 0 END),0),0) DESC");
$n=0;
foreach ($ppv as $r) {
    if ($r->p24==null || $r->p25==null) continue;
    $var = ($r->p25/$r->p24 - 1)*100;
    printf("  %-14s p24=%.3f p25=%.3f var=%+6.1f%%  %s\n", $r->cod_articulo, $r->p24, $r->p25, $var, substr(trim($r->descripcion),0,28));
    if (++$n>=12) break;
}
printf("  tiempo: %.2fs\n", microtime(true)-$t0);