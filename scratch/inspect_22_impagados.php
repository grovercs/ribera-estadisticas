<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$invoices = [
    '141000369', '141000163', '141000102', '141000277', '141000735', '141000223',
    '918100001', '918100002', '918100004', '918100006', '918100007', '918100008',
    '918100003', '918100005',
    '262002135', '262002433', '262002434', '252007713', '261000496', '251002536',
    '522001113', '522004298'
];

$placeholders = implode(',', array_fill(0, count($invoices), '?'));
$res = $db->select("
    SELECT 
        v.cod_factura,
        f.cod_almacen,
        v.cod_forma_liquidacion,
        v.emitido,
        v.importe,
        v.importe_cobrado,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE v.cod_factura IN ($placeholders)
", $invoices);

echo "=== 22 IMPAGADOS DETAILS ===\n";
$sum = 0;
foreach ($res as $r) {
    echo sprintf("Invoice: %9s | Alm: %4s | FP: %5s | Emitido: %s | Importe: %9.2f | Pendiente: %9.2f\n",
        $r->cod_factura,
        $r->cod_almacen ?? 'NULL',
        $r->cod_forma_liquidacion,
        $r->emitido,
        $r->importe,
        $r->pendiente
    );
    $sum += $r->pendiente;
}
echo "Total Sum of Pendiente: " . number_format($sum, 2) . "\n";
echo "Count: " . count($res) . "\n";
