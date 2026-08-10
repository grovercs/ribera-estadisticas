<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== GRID SEARCH FOR 22 IMPAGADOS (VIELHA) AND 706 PENDIENTES (PONT DE SUERT) ===\n";

// Let's first dump the list of all pending vencimientos where FP starts with Z
// and see if we can find a subset of 22 for Vielha (Store 2).
$zVencimientos = $db->select("
    SELECT 
        v.cod_factura,
        v.fecha_factura,
        v.fecha_vencimiento,
        v.cod_forma_liquidacion,
        v.emitido,
        v.importe,
        v.importe_cobrado,
        (v.importe - v.importe_cobrado) as pendiente,
        f.cod_almacen
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
      AND v.cod_forma_liquidacion LIKE 'Z%'
    ORDER BY f.cod_almacen, v.fecha_factura DESC
");

echo "Total Z-FP pending: " . count($zVencimientos) . "\n";

// Let's look at those with cod_almacen = 2 or 3rd digit = '2'
$store2Z = [];
foreach ($zVencimientos as $v) {
    $digit = substr((string)$v->cod_factura, 2, 1);
    if ($v->cod_almacen == 2 || $digit == '2') {
        $store2Z[] = $v;
    }
}
echo "Total Store 2 Z-FP pending: " . count($store2Z) . "\n";

// Let's print them out to inspect their columns
echo "\n=== Store 2 Z-FP pending list ===\n";
foreach ($store2Z as $i => $v) {
    echo sprintf("[%3d] Factura: %9s | FP: %4s | Emitido: %s | Fecha F: %s | Imp: %10.2f | Cob: %10.2f | Pend: %10.2f | Alm: %s\n",
        $i+1,
        $v->cod_factura,
        $v->cod_forma_liquidacion,
        $v->emitido,
        substr($v->fecha_factura, 0, 10),
        $v->importe,
        $v->importe_cobrado,
        $v->pendiente,
        $v->cod_almacen ?? 'NULL'
    );
}

// Now let's do the same for Store 1 (Pont de Suert) and list all pending invoices (both Z and non-Z)
// to see if we can find a subset of 706 with total 343,233.17 €.
$allStore1 = $db->select("
    SELECT 
        v.cod_factura,
        v.fecha_factura,
        v.fecha_vencimiento,
        v.cod_forma_liquidacion,
        v.emitido,
        v.importe,
        v.importe_cobrado,
        (v.importe - v.importe_cobrado) as pendiente,
        f.cod_almacen
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
    ORDER BY v.fecha_factura DESC
");

$store1Invoices = [];
foreach ($allStore1 as $v) {
    $digit = substr((string)$v->cod_factura, 2, 1);
    if ($v->cod_almacen == 1 || $digit == '1') {
        $store1Invoices[] = $v;
    }
}
echo "\nTotal Store 1 pending (all FPs): " . count($store1Invoices) . "\n";
$sumStore1 = array_sum(array_map(fn($v) => $v->pendiente, $store1Invoices));
echo "Sum Store 1 pending (all FPs): " . number_format($sumStore1, 2) . "\n";
