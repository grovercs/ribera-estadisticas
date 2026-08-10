<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== COMPANIES AND STORES IN VENCIMIENTOS_FACTURAS ===\n";
$res = $db->select("
    SELECT 
        v.cod_empresa,
        f.cod_almacen,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY v.cod_empresa, f.cod_almacen
");
foreach ($res as $r) {
    echo "  Empresa: {$r->cod_empresa} | Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}

echo "\n=== COMPANIES AND STORES (3RD DIGIT) IN VENCIMIENTOS_FACTURAS ===\n";
$res2 = $db->select("
    SELECT 
        v.cod_empresa,
        SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) as store_digit,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY v.cod_empresa, SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1)
");
foreach ($res2 as $r) {
    echo "  Empresa: {$r->cod_empresa} | Digit: {$r->store_digit} | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}

echo "\n=== CHEAP CHECK FOR TIPO_FACTURA IN VENCIMIENTOS_FACTURAS ===\n";
$res3 = $db->select("
    SELECT 
        v.tipo_factura,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY v.tipo_factura
");
foreach ($res3 as $r) {
    echo "  Tipo Factura: {$r->tipo_factura} | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}
