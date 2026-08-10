<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$cands = [
    'facturas_compras_negativas_modelo_349',
    'facturas_compra_cc_concepto',
    'facturas_compra_cc_concepto_prov',
    'conceptos_central_compra',
    'facturas_compra_cc_cabecera',
    'facturas_firmadas',
    'sii_facturas_compras_eliminadas',
];

foreach ($cands as $tbl) {
    echo "\n===== $tbl =====\n";
    try {
        $cols = $erp->select("SELECT name FROM sys.columns WHERE object_id = object_id('$tbl') ORDER BY column_id");
        $names = array_map(fn($r) => $r->name, $cols);
        echo "  cols: " . implode(', ', $names) . "\n";
        $cnt = $erp->select("SELECT COUNT(*) c FROM $tbl")[0]->c;
        echo "  rows: $cnt\n";
    } catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }
}

// Buscar cualquier tabla con columna fecha_factura o fecha + importe_impuestos (posible fuente de gastos)
echo "\n=== Tablas con columna 'fecha_factura' ===\n";
foreach ($erp->select("SELECT t.name FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id WHERE c.name='fecha_factura' ORDER BY t.name") as $r)
    echo "  $r->name\n";

echo "\n=== Tablas con columna 'importe_impuestos' ===\n";
foreach ($erp->select("SELECT t.name FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id WHERE c.name='importe_impuestos' ORDER BY t.name") as $r)
    echo "  $r->name\n";