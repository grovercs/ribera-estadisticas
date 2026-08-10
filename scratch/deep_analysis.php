<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== ANALISIS PROFUNDO: SERIES 1, 8, 9 ===\n\n";

// Series 8 and 9 are the ZJUZ invoices from Store 1 (Pont de Suert)
// Let's check if the legacy counts series 1+8+9 together as "Pont de Suert"

$combos = [
    ['name' => 'Digit 1+8+9 - venc>0',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) IN ('1','8','9')"],
    ['name' => 'Digit 1+8+9 - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) IN ('1','8','9')
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    ['name' => 'Digit 1+8+9 - only ZJUZ/ZIMP',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) IN ('1','8','9')
               AND v.cod_forma_liquidacion IN ('ZJUZ','ZIMP')"],
    ['name' => 'Digit 1+8+9 - dedup facturas',
     'sql' => "SELECT COUNT(*) as cnt, SUM(pendiente) as total FROM (
                 SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
                 FROM vencimientos_facturas v
                 WHERE SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) IN ('1','8','9')
                 GROUP BY v.cod_factura HAVING SUM(v.importe - v.importe_cobrado) > 0
               ) t"],
    // Now: what if the 706 corresponds to alm=1 + alm=2 COMBINED (both stores)
    // but with a specific date cutoff?
    ['name' => 'Alm 1+2 - venc>0 - from 2020',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND f.cod_almacen IN (1,2) AND f.fecha_factura >= '2020-01-01'"],
    ['name' => 'Alm 1+2 - venc>0 - from 2022',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND f.cod_almacen IN (1,2) AND f.fecha_factura >= '2022-01-01'"],
    ['name' => 'Alm 1+2 - venc>0 - from 2023',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND f.cod_almacen IN (1,2) AND f.fecha_factura >= '2023-01-01'"],
    ['name' => 'Alm 1+2 - venc>0 - from 2024',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND f.cod_almacen IN (1,2) AND f.fecha_factura >= '2024-01-01'"],
    // HYPOTHESIS: Maybe the legacy count of 706 combines ALL stores EXCLUDING Z-series
    // and specifically uses COD_ALMACEN = 2 or cod_sede_replica = 2
    // Check: cod_sede_replica column in facturas_ventas_cabecera?
    ['name' => 'ALL - venc>0 - excl Z - from 2023',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'
               AND f.fecha_factura >= '2023-01-01'"],
    ['name' => 'ALL - venc>0 - excl Z - from 2024',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'
               AND f.fecha_factura >= '2024-01-01'"],
    ['name' => 'ALL - venc>0 - excl Z - from 2025',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'
               AND f.fecha_factura >= '2025-01-01'"],
    // Check vencimiento date filters instead of factura date
    ['name' => 'Alm 1+2 - vto<=hoy - all FP',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND f.cod_almacen IN (1,2) AND v.fecha_vencimiento <= GETDATE()"],
    ['name' => 'Alm 1+2 - vto futuro - all FP',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND f.cod_almacen IN (1,2) AND v.fecha_vencimiento > GETDATE()"],
];

foreach ($combos as $c) {
    try {
        $res = $db->select($c['sql']);
        $cnt = $res[0]->cnt ?? 0;
        $total = $res[0]->total ?? 0;
        $marker = ($cnt >= 700 && $cnt <= 715) ? ' *** NEAR TARGET! ***' : '';
        echo sprintf("%-45s -> Count: %4d | Sum: %12.2f%s\n", $c['name'], $cnt, $total, $marker);
    } catch (\Exception $e) {
        echo sprintf("%-45s -> ERROR: %s\n", $c['name'], substr($e->getMessage(), 0, 60));
    }
}

// Year by year breakdown for alm=1 to see where 343k came from
echo "\n--- Pont (alm=1) por año de factura ---\n";
$byYear = $db->select("
    SELECT YEAR(f.fecha_factura) as anyo,
           COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
    FROM vencimientos_facturas v
    INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
    GROUP BY YEAR(f.fecha_factura)
    ORDER BY anyo
");
foreach ($byYear as $r) {
    echo sprintf("  %d | cnt=%d | sum=%.2f\n", $r->anyo, $r->cnt, $r->total);
}
