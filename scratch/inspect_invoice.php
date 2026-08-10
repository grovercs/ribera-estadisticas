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

echo "=== DETAILED INVOICE DETAILS ===\n";
foreach ($list as $cod) {
    $res = $db->select("
        SELECT 
            v.cod_factura,
            v.cod_forma_liquidacion,
            v.fecha_vencimiento,
            v.emitido,
            f.fecha_factura,
            f.cod_almacen,
            f.razon_social,
            v.importe,
            v.importe_cobrado,
            (v.importe - v.importe_cobrado) as pendiente
        FROM vencimientos_facturas v
        LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE v.cod_factura = ?
    ", [$cod]);
    foreach ($res as $r) {
        echo sprintf("Inv: %s | Store: %s | Date: %s | Venc: %s | Emitido: %s | FP: %s | Imp: %.2f | Pend: %.2f | Cliente: %s\n",
            $r->cod_factura,
            $r->cod_almacen ?? 'NULL',
            $r->fecha_factura,
            $r->fecha_vencimiento,
            $r->emitido ?? 'NULL',
            $r->cod_forma_liquidacion,
            $r->importe,
            $r->pendiente,
            substr($r->razon_social, 0, 15)
        );
    }
}
