<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== LISTING ERP TABLES ===\n";

$res = $db->select("
    SELECT TABLE_NAME 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
");

foreach ($res as $r) {
    $name = $r->TABLE_NAME;
    if (stripos($name, 'venc') !== false || stripos($name, 'cobro') !== false || stripos($name, 'fact') !== false || stripos($name, 'pend') !== false) {
        echo "  $name\n";
    }
}
