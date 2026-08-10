<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Laspeyres con cesta fija y "carry base price" para articulos no comprados en el mes:
// indice = 100 * ( SUM_comprados(p*q0) + (coste_base_total - SUM_comprados(p0*q0)) ) / coste_base_total
//        = 100 * ( 1 + (SUM_comprados(p*q0) - SUM_comprados(p0*q0)) / coste_base_total )
// Capeo p/p0 a [0.1, 10] para descartar distorsiones (notas de abono / cambio de unidad).
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
    HAVING SUM(CAST(l.cantidad AS float)) > 10
),
tot AS (SELECT SUM(p0*q0) AS tbc FROM base),
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
    100 * (1 + (SUM(CASE WHEN m.p/b.p0 > 10 THEN b.p0*10 WHEN m.p/b.p0 < 0.1 THEN b.p0*0.1 ELSE m.p END * b.q0)
                - SUM(b.p0 * b.q0)) / (SELECT tbc FROM tot)) AS indice,
    COUNT(*) comprados
FROM mensual m JOIN base b ON m.cod_articulo = b.cod_articulo
GROUP BY m.y, m.m
ORDER BY m.y, m.m");
$rBase = $erp->select("SELECT COUNT(*) c, SUM(p0*q0) tbc FROM (
    SELECT l.cod_articulo, SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) p0, SUM(CAST(l.cantidad AS float)) q0
    FROM hist_compras_linea l JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0 AND c.fecha_compra>='20240101' AND c.fecha_compra<'20240401'
    GROUP BY l.cod_articulo HAVING SUM(CAST(l.cantidad AS float))>10) x")[0];
printf("=== Indice Laspeyres (cesta fija, carry base, base 100 = ene-mar 2024) ===\n");
printf("cesta base: %d articulos, coste base=%.0f EUR\n", $rBase->c, $rBase->tbc);
foreach ($rows as $r) printf("  %d-%02d indice=%6.1f  comprados=%d\n", $r->y, $r->m, $r->indice, $r->comprados);
printf("  tiempo: %.2fs\n", microtime(true)-$t0);