<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== GRID: DIFERENTES COMBINACIONES DE FILTROS ===\n\n";

// Key insight: the difference between our 518/353841 and target 706/343233 is big.
// Let's test counting by vencimientos (not facturas) and excluding Z-series
// Also test counting vencimientos with importe > 0 (not sum HAVING > 0)

$tests = [
    // [description, store_filter, fp_filter, pending_filter]
];

// Let's do a comprehensive matrix in SQL
$combos = [
    ['name' => 'Pont - venc>0 - all FPs',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1"],
    ['name' => 'Pont - venc>0 - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    ['name' => 'Pont - venc<>0 - all FPs',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) <> 0 AND f.cod_almacen = 1"],
    ['name' => 'Pont - venc<>0 - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) <> 0 AND f.cod_almacen = 1
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    ['name' => 'Pont - venc>0 - emitido=S',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
               AND v.emitido = 'S'"],
    ['name' => 'Pont - venc>0 - emitido!=S',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
               AND (v.emitido IS NULL OR v.emitido <> 'S')"],
    ['name' => 'Pont - venc>0 - emitido=N',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
               AND v.emitido = 'N'"],
    ['name' => 'Pont - venc>0 - emitido IS NULL',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
               AND v.emitido IS NULL"],
    ['name' => 'Pont+Vielha - venc>0 - all FPs',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen IN (1, 2)"],
    ['name' => 'Pont+Vielha - venc>0 - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen IN (1, 2)
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    // New: what if we count by factura (GROUP BY) instead of vencimientos?
    ['name' => 'Pont - facturas dedup - all FPs',
     'sql' => "SELECT COUNT(*) as cnt, SUM(pendiente) as total FROM (
                 SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
                 FROM vencimientos_facturas v
                 INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
                 WHERE f.cod_almacen = 1
                 GROUP BY v.cod_factura HAVING SUM(v.importe - v.importe_cobrado) > 0
               ) t"],
    ['name' => 'Pont - facturas dedup - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(pendiente) as total FROM (
                 SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
                 FROM vencimientos_facturas v
                 INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
                 WHERE f.cod_almacen = 1 AND v.cod_forma_liquidacion NOT LIKE 'Z%'
                 GROUP BY v.cod_factura HAVING SUM(v.importe - v.importe_cobrado) > 0
               ) t"],
    // New: check emitido IS NULL (normal invoices without remittance)
    ['name' => 'Pont - emitido NULL - venc>0',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen = 1
               AND v.emitido IS NULL AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    // Check: what does the legacy system call "pendientes"? Maybe it's importe != importe_cobrado (not strict >0)?
    ['name' => 'ALL - importe != cobrado',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE v.importe <> v.importe_cobrado"],
];

foreach ($combos as $c) {
    $res = $db->select($c['sql']);
    echo sprintf("%-45s -> Count: %4d | Sum: %12.2f\n",
        $c['name'],
        $res[0]->cnt,
        $res[0]->total ?? 0
    );
}

// Extra: check what emitido values exist in vencimientos_facturas for Pont
echo "\n--- Emitido values for Pont (almacen=1) ---\n";
$em = $db->select("
    SELECT v.emitido, COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
    FROM vencimientos_facturas v
    INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
    WHERE f.cod_almacen = 1 AND (v.importe - v.importe_cobrado) > 0
    GROUP BY v.emitido
");
foreach ($em as $r) {
    echo sprintf("  emitido=%s | cnt=%d | sum=%.2f\n", $r->emitido ?? 'NULL', $r->cnt, $r->total);
}
