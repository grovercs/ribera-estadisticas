<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== TOTAL VENCIMIENTOS BY STORE (INCLUDING PAID) ===\n";

$res = $db->select("
    SELECT 
        f.cod_almacen,
        SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) as store_digit,
        COUNT(*) as cnt,
        SUM(v.importe) as total_importe,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    GROUP BY f.cod_almacen, SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1)
");

foreach ($res as $r) {
    echo sprintf("Almacen: %s | Digit: %s | Count: %d | Importe: %s | Pend: %s\n",
        $r->cod_almacen ?? 'NULL',
        $r->store_digit,
        $r->cnt,
        number_format($r->total_importe, 2),
        number_format($r->total_pend, 2)
    );
}
