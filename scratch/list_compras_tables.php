<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== Tablas relacionadas con compras/facturas/gastos ===\n";
$t = $erp->select("SELECT name FROM sys.tables WHERE name LIKE '%compra%' OR name LIKE '%factura%' OR name LIKE '%gasto%' ORDER BY name");
foreach ($t as $r) echo "  $r->name\n";

echo "\n=== Columnas de hist_compras_cabecera ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) AS t FROM sys.columns WHERE object_id = object_id('hist_compras_cabecera') ORDER BY column_id") as $r)
    printf("  %-25s %s\n", $r->name, $r->t);