<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== PENDIENTES CON SUBALMACENES ===\n\n";

// Store 1 group: almacen 1, 11, 12 (Pont de Suert + roturas + faltantes)
// Store 2 group: almacen 2, 21, 22 (Vielha + roturas + faltantes)

$res = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(DISTINCT v.cod_factura) as facturas,
        COUNT(*) as vencimientos,
        SUM(v.importe - v.importe_cobrado) as importe
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY f.cod_almacen
    ORDER BY f.cod_almacen
");

echo "Desglose por almacen (pediente > 0):\n";
foreach ($res as $r) {
    echo sprintf("  Almacen: %4s | Facturas: %4d | Vencimientos: %4d | Importe: %12.2f\n",
        $r->cod_almacen ?? 'NULL',
        $r->facturas,
        $r->vencimientos,
        $r->importe
    );
}

// Now group Pont (1+11+12) vs Vielha (2+21+22)
echo "\n--- Agrupado por sede ---\n";

$pont = array_filter((array)$res, fn($r) => in_array($r->cod_almacen, [1, 11, 12]));
$vielha = array_filter((array)$res, fn($r) => in_array($r->cod_almacen, [2, 21, 22]));

$pontFacturas = array_sum(array_column($pont, 'facturas'));
$pontVencimientos = array_sum(array_column($pont, 'vencimientos'));
$pontImporte = array_sum(array_column($pont, 'importe'));

$vielhaFacturas = array_sum(array_column($vielha, 'facturas'));
$vielhaVencimientos = array_sum(array_column($vielha, 'vencimientos'));
$vielhaImporte = array_sum(array_column($vielha, 'importe'));

echo sprintf("Pont de Suert (1+11+12): Facturas=%d | Vencimientos=%d | Importe=%.2f\n",
    $pontFacturas, $pontVencimientos, $pontImporte);
echo sprintf("Vielha (2+21+22):        Facturas=%d | Vencimientos=%d | Importe=%.2f\n",
    $vielhaFacturas, $vielhaVencimientos, $vielhaImporte);

// Also check: deduplicate by cod_factura for Pont
echo "\n--- Pont deduplicado por factura (1 row per invoice) ---\n";
$pont2 = $db->select("
    SELECT 
        COUNT(DISTINCT v.cod_factura) as facturas,
        SUM(sub.pendiente) as importe
    FROM (
        SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
        FROM vencimientos_facturas v
        LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE (v.importe - v.importe_cobrado) > 0
          AND f.cod_almacen IN (1, 11, 12)
        GROUP BY v.cod_factura
    ) sub
    CROSS APPLY (SELECT sub.cod_factura) v(cod_factura)
");

// Simpler approach
$pont3 = $db->select("
    SELECT 
        COUNT(*) as cnt,
        SUM(pendiente) as total
    FROM (
        SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
        FROM vencimientos_facturas v
        INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE f.cod_almacen IN (1, 11, 12)
        GROUP BY v.cod_factura
        HAVING SUM(v.importe - v.importe_cobrado) > 0
    ) t
");
echo sprintf("  Pont dedup (HAVING > 0): Count=%d | Importe=%.2f\n",
    $pont3[0]->cnt, $pont3[0]->total);

// Check for Vielha too
$vielha3 = $db->select("
    SELECT 
        COUNT(*) as cnt,
        SUM(pendiente) as total
    FROM (
        SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
        FROM vencimientos_facturas v
        INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE f.cod_almacen IN (2, 21, 22)
        GROUP BY v.cod_factura
        HAVING SUM(v.importe - v.importe_cobrado) > 0
    ) t
");
echo sprintf("  Vielha dedup (HAVING > 0): Count=%d | Importe=%.2f\n",
    $vielha3[0]->cnt, $vielha3[0]->total);

// Also test: ALL stores dedup
$all3 = $db->select("
    SELECT 
        COUNT(*) as cnt,
        SUM(pendiente) as total
    FROM (
        SELECT v.cod_factura, SUM(v.importe - v.importe_cobrado) as pendiente
        FROM vencimientos_facturas v
        GROUP BY v.cod_factura
        HAVING SUM(v.importe - v.importe_cobrado) > 0
    ) t
");
echo sprintf("  ALL dedup (HAVING > 0): Count=%d | Importe=%.2f\n",
    $all3[0]->cnt, $all3[0]->total);
