<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== COLUMNS OF facturas_ventas_cabecera ===\n";

$res = $db->select("
    SELECT COLUMN_NAME, DATA_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'facturas_ventas_cabecera'
");

foreach ($res as $r) {
    echo "  {$r->COLUMN_NAME} ({$r->DATA_TYPE})\n";
}
