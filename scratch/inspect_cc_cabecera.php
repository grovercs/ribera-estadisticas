<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== Columnas de facturas_compra_cc_cabecera ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) AS t FROM sys.columns WHERE object_id = object_id('facturas_compra_cc_cabecera') ORDER BY column_id") as $r)
    printf("  %-30s %s\n", $r->name, $r->t);

echo "\n=== Totales facturas_compra_cc_cabecera (sin GROUP) ===\n";
try {
    foreach ($erp->select("SELECT COUNT(*) c FROM facturas_compra_cc_cabecera") as $r) echo "  total rows: $r->c\n";
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }