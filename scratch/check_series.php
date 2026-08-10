<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== BUSQUEDA POR NUMERO DE SERIE DE FACTURA ===\n\n";

// The invoice number format appears to be: YYSXXXXX
// where YY = year (26), S = series/store digit (1 or 2), XXXXX = sequence
// Let's test filtering by the 3rd digit of cod_factura (the series digit)
// Series 1 = Pont de Suert, Series 2 = Vielha

// Get all vencimientos with their series digit
$combos = [
    ['name' => '3er digito=1 - venc>0',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'"],
    ['name' => '3er digito=2 - venc>0',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '2'"],
    ['name' => '3er digito=1 - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    ['name' => '3er digito=2 - excl Z',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               WHERE (v.importe - v.importe_cobrado) > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '2'
               AND v.cod_forma_liquidacion NOT LIKE 'Z%'"],
    // Check mixing: cod_almacen=1 OR series digit=1
    ['name' => 'alm=1 OR digit=1 - venc>0',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
               FROM vencimientos_facturas v
               LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
               WHERE (v.importe - v.importe_cobrado) > 0
               AND (f.cod_almacen = 1 OR SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1')"],
    // What about importe > 0 on the vencimiento itself (not the difference)?
    ['name' => '3er digito=1 - importe>0 (bruto)',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe) as total
               FROM vencimientos_facturas v
               WHERE v.importe > 0
               AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'"],
    // Digit 1 with sum > 0 per invoice
    ['name' => 'digit=1 facturas dedup - all',
     'sql' => "SELECT COUNT(*) as cnt, SUM(pendiente) as total FROM (
                 SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
                 FROM vencimientos_facturas v
                 WHERE SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
                 GROUP BY v.cod_factura HAVING SUM(v.importe - v.importe_cobrado) > 0
               ) t"],
    ['name' => 'digit=2 facturas dedup - all',
     'sql' => "SELECT COUNT(*) as cnt, SUM(pendiente) as total FROM (
                 SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
                 FROM vencimientos_facturas v
                 WHERE SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '2'
                 GROUP BY v.cod_factura HAVING SUM(v.importe - v.importe_cobrado) > 0
               ) t"],
    // CRUCIAL: combined (alm=1 + digit=1) deduped -- eliminates overlap
    ['name' => 'alm=1 OR digit=1 dedup',
     'sql' => "SELECT COUNT(*) as cnt, SUM(pendiente) as total FROM (
                 SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
                 FROM vencimientos_facturas v
                 LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
                 WHERE (f.cod_almacen = 1 OR SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1')
                 GROUP BY v.cod_factura HAVING SUM(v.importe - v.importe_cobrado) > 0
               ) t"],
    // Maybe the pendientes reference value comes from cobros_vencimientos_facturas ?
    ['name' => 'cobros_vencimientos digit=1',
     'sql' => "SELECT COUNT(*) as cnt, SUM(v.importe - ISNULL(c.total_cobrado, 0)) as total
               FROM vencimientos_facturas v
               LEFT JOIN (
                   SELECT cod_factura, tipo_factura, cod_empresa, numero, SUM(importe) as total_cobrado
                   FROM cobros_vencimientos_facturas
                   GROUP BY cod_factura, tipo_factura, cod_empresa, numero
               ) c ON v.cod_factura = c.cod_factura AND v.tipo_factura = c.tipo_factura AND v.cod_empresa = c.cod_empresa AND v.numero = c.numero
               WHERE SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
               AND (v.importe - ISNULL(c.total_cobrado, 0)) > 0"],
];

foreach ($combos as $c) {
    try {
        $res = $db->select($c['sql']);
        echo sprintf("%-45s -> Count: %4d | Sum: %12.2f\n",
            $c['name'],
            $res[0]->cnt,
            $res[0]->total ?? 0
        );
    } catch (\Exception $e) {
        echo sprintf("%-45s -> ERROR: %s\n", $c['name'], $e->getMessage());
    }
}

// Extra: show distribution of vencimientos by series digit
echo "\n--- Distribucion por 3er digito cod_factura (venc > 0) ---\n";
$dist = $db->select("
    SELECT SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) as serie,
           COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as total
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1)
    ORDER BY serie
");
foreach ($dist as $r) {
    echo sprintf("  serie=%s | cnt=%d | sum=%.2f\n", $r->serie, $r->cnt, $r->total);
}
