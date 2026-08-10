<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

foreach (['compras1', 'progresion_compras'] as $v) {
    echo "\n===== VISTA $v =====\n";
    try {
        $cols = $erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id = object_id('$v') ORDER BY column_id");
        foreach ($cols as $r) printf("  %-28s %s\n", $r->name, $r->t);
        $cnt = $erp->select("SELECT COUNT(*) c FROM $v")[0]->c;
        echo "  rows: $cnt\n";
        // sample
        echo "  sample TOP 3:\n";
        $s = $erp->select("SELECT TOP 3 * FROM $v");
        foreach ($s as $row) { print_r($row); }
    } catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }
}