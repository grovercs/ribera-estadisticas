<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$list = [
    '242006171', '262002042', '262002588', '262002613', '152001531', 
    '232001722', '222003540', '262002941', '262002943', '262002942', 
    '262002940', '262002944', '261000879', '261001010', '262003198', 
    '262003199', '262003197', '262003200'
];

echo "=== CHECKING USER LIST IN vencimientos_facturas ===\n";
foreach ($list as $cod) {
    $res = $db->select("
        SELECT cod_factura, cod_forma_liquidacion, (importe - importe_cobrado) as pendiente
        FROM vencimientos_facturas
        WHERE cod_factura = ?
    ", [$cod]);
    if (empty($res)) {
        echo "Invoice: $cod | NOT FOUND\n";
    } else {
        foreach ($res as $r) {
            echo "Invoice: {$r->cod_factura} | FP: {$r->cod_forma_liquidacion} | Pendiente: {$r->pendiente}\n";
        }
    }
}
