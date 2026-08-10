<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$names = [
    '%BARDAJI%',
    '%CHELARU%',
    '%EQUIPAMIENT%',
    '%Jose Antonio%',
    '%Marta ROLLAN%',
    '%PERICOT%',
    '%REBEI%',
    '%RGARAN%'
];

echo "=== FINDING CANDIDATES FOR THE 22 IMPAGADOS ===\n";
foreach ($names as $namePattern) {
    $res = $db->select("
        SELECT 
            v.cod_factura,
            v.fecha_factura,
            v.fecha_vencimiento,
            v.cod_forma_liquidacion,
            v.emitido,
            v.importe,
            v.importe_cobrado,
            (v.importe - v.importe_cobrado) as pendiente,
            f.cod_cliente,
            (SELECT TOP 1 c.razon_social FROM clientes c WHERE c.cod_cliente = f.cod_cliente) as razon_social
        FROM vencimientos_facturas v
        INNER JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE (v.importe - v.importe_cobrado) <> 0
          AND EXISTS (
              SELECT 1 FROM clientes c 
              WHERE c.cod_cliente = f.cod_cliente 
                AND c.razon_social LIKE ?
          )
    ", [$namePattern]);
    
    echo "Pattern: $namePattern | Count: " . count($res) . "\n";
    foreach ($res as $r) {
        echo sprintf("  Invoice: %s | Client: %s - %s | FP: %s | Emitido: %s | Pendiente: %.2f\n",
            $r->cod_factura,
            $r->cod_cliente,
            $r->razon_social,
            $r->cod_forma_liquidacion,
            $r->emitido,
            $r->pendiente
        );
    }
}
