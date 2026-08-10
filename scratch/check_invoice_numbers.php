<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$testInvoices = [
    '242006171',
    '262002042',
    '262002588',
    '262002613',
    '152001531',
    '261000879',
    '261001010',
    '252007567'
];

echo "=== CHECK IN VENCIMIENTOS_FACTURAS ===\n";
foreach ($testInvoices as $cod) {
    $res = $db->select("
        SELECT 
            cod_factura,
            cod_empresa,
            tipo_factura,
            numero,
            fecha_factura,
            fecha_vencimiento,
            cod_forma_liquidacion,
            emitido,
            importe,
            importe_cobrado,
            (importe - importe_cobrado) as pendiente
        FROM vencimientos_facturas
        WHERE cod_factura = ?
    ", [$cod]);
    
    if (empty($res)) {
        echo "Invoice $cod: NOT FOUND in vencimientos_facturas\n";
    } else {
        foreach ($res as $r) {
            echo sprintf("  Invoice: %s | Empresa: %s | Tipo: %s | Num: %s | FP: %s | Emitido: %s | Imp: %.2f | Cob: %.2f | Pend: %.2f\n",
                $r->cod_factura,
                $r->cod_empresa,
                $r->tipo_factura,
                $r->numero,
                $r->cod_forma_liquidacion,
                $r->emitido,
                $r->importe,
                $r->importe_cobrado,
                $r->pendiente
            );
            
            // Check in cabecera
            $cab = $db->select("
                SELECT cod_almacen, cod_cliente
                FROM facturas_ventas_cabecera
                WHERE cod_factura = ? AND tipo_factura = ? AND cod_empresa = ?
            ", [$r->cod_factura, $r->tipo_factura, $r->cod_empresa]);
            
            if (empty($cab)) {
                echo "    -> NOT FOUND in facturas_ventas_cabecera!\n";
            } else {
                echo sprintf("    -> Cabecera Almacen: %s | Cliente: %s\n",
                    $cab[0]->cod_almacen,
                    $cab[0]->cod_cliente
                );
            }
        }
    }
}
