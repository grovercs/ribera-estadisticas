<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Indice Laspeyres: cesta fija del periodo base (2024-01..2024-03), cantidades q0 fijas,
// precio p0 en base. Para cada mes: indice = 100 * SUM(p_mes * q0) / SUM(p0 * q0).
// Solo articulos presentes en la cesta base con q0 > 5 (estabilidad).
$t0 = microtime(true);
$rows = $erp->select("
WITH base AS (
    SELECT l.cod_articulo,
        SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) AS p0,
        SUM(CAST(l.cantidad AS float)) AS q0
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0
      AND c.fecha_compra>='20240101' AND c.fecha_compra<'20240401'
    GROUP BY l.cod_articulo
    HAVING SUM(CAST(l.cantidad AS float)) > 5
),
mensual AS (
    SELECT l.cod_articulo, YEAR(c.fecha_compra) y, MONTH(c.fecha_compra) m,
        SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) AS p
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0
      AND c.fecha_compra>='20240101' AND c.fecha_compra<'20270101'
    GROUP BY l.cod_articulo, YEAR(c.fecha_compra), MONTH(c.fecha_compra)
)
SELECT m.y, m.m,
    100 * SUM(m.p * b.q0) / NULLIF(SUM(b.p0 * b.q0), 0) AS indice,
    COUNT(*) articulos
FROM mensual m JOIN base b ON m.cod_articulo = b.cod_articulo
GROUP BY m.y, m.m
ORDER BY m.y, m.m");
printf("=== Indice Laspeyres de precio de compra (base 100 = ene-mar 2024) ===\n");
printf("cesta articulos base: (calculada)\n");
foreach ($rows as $r) printf("  %d-%02d indice=%6.1f  articulos=%d\n", $r->y, $r->m, $r->indice, $r->articulos);
printf("  tiempo: %.2fs\n", microtime(true)-$t0);