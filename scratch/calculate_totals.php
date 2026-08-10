<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "==================== ANALYSIS WITH PENDING <> 0 ====================\n";

// 1. Total pending in vencimientos_facturas (all stores)
$resAll = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY f.cod_almacen
");
echo "Total pending in vencimientos_facturas (grouped by store):\n";
foreach ($resAll as $r) {
    echo "  Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}

// 2. What about with v.importe - v.importe_cobrado <> 0 and FP exclusions?
// Let's see what happens if we group by cod_forma_liquidacion
$resFP = $db->select("
    SELECT 
        v.cod_forma_liquidacion,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) <> 0
    GROUP BY v.cod_forma_liquidacion
    ORDER BY total_pend DESC
");
echo "\nPending grouped by FP (with <> 0):\n";
foreach ($resFP as $r) {
    echo "  FP: {$r->cod_forma_liquidacion} | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}

// 3. Let's count how many have FP starting with 'Z'
$resZ = $db->select("
    SELECT 
        f.cod_almacen,
        v.cod_forma_liquidacion,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
      AND v.cod_forma_liquidacion LIKE 'Z%'
    GROUP BY f.cod_almacen, v.cod_forma_liquidacion
");
echo "\nForms starting with Z (grouped by store and FP):\n";
foreach ($resZ as $r) {
    echo "  Store: " . ($r->cod_almacen ?? 'NULL') . " | FP: {$r->cod_forma_liquidacion} | Count: {$r->cnt} | Sum: " . number_format($r->total_pend, 2) . "\n";
}
