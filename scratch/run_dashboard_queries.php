<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== RUNNING CURRENT CONTROLLER QUERIES ===\n";

$impagadosRaw = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as tickets,
        SUM(v.importe - v.importe_cobrado) as importe
    FROM devoluciones_vencimientos_ventas d
    INNER JOIN vencimientos_facturas v ON d.cod_factura_destino = v.cod_factura 
        AND d.tipo_factura_destino = v.tipo_factura 
        AND d.cod_empresa_destino = v.cod_empresa 
        AND d.numero_destino = v.numero
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY f.cod_almacen
");

echo "\nImpagados:\n";
foreach ($impagadosRaw as $r) {
    echo "  Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->tickets} | Sum: " . number_format($r->importe, 2) . "\n";
}

$pendientesRaw = $db->select("
    SELECT 
        f.cod_almacen,
        COUNT(*) as tickets,
        SUM(v.importe - v.importe_cobrado) as importe
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
      AND NOT EXISTS (
          SELECT 1 FROM devoluciones_vencimientos_ventas d
          WHERE d.cod_factura_destino = v.cod_factura
            AND d.tipo_factura_destino = v.tipo_factura
            AND d.cod_empresa_destino = v.cod_empresa
            AND d.numero_destino = v.numero
      )
    GROUP BY f.cod_almacen
");

echo "\nPendientes:\n";
foreach ($pendientesRaw as $r) {
    echo "  Store: " . ($r->cod_almacen ?? 'NULL') . " | Count: {$r->tickets} | Sum: " . number_format($r->importe, 2) . "\n";
}
