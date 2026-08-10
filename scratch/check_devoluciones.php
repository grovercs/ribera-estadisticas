<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$invoices = [
    // ZJUZ
    '141000369', '141000163', '141000102', '141000277', '141000735', '141000223',
    '918100001', '918100002', '918100004', '918100006', '918100007', '918100008',
    '918100003', '918100005',
    // ZIMP
    '262002135', '262002433', '262002434', '252007713', '261000496', '251002536',
    // Z650
    '522001113', '522004298'
];

echo "=== CHECKING IN devoluciones_vencimientos_ventas ===\n";
foreach ($invoices as $cod) {
    $res = $db->select("
        SELECT COUNT(*) as cnt
        FROM devoluciones_vencimientos_ventas
        WHERE cod_factura_destino = ?
    ", [$cod]);
    
    echo "Invoice: $cod | In devoluciones count: {$res[0]->cnt}\n";
}
