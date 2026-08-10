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

$placeholders = implode(',', array_fill(0, count($invoices), '?'));
$res = $db->select("
    SELECT 
        v.cod_factura,
        v.cod_forma_liquidacion,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    WHERE v.cod_factura IN ($placeholders)
", $invoices);

$sum = 0;
foreach ($res as $r) {
    echo "Factura: {$r->cod_factura} | FP: {$r->cod_forma_liquidacion} | Pendiente: {$r->pendiente}\n";
    $sum += $r->pendiente;
}

echo "TOTAL SUM: " . number_format($sum, 2) . "\n";
echo "COUNT: " . count($res) . "\n";
