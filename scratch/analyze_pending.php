<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== Group pending by cod_forma_liquidacion ===\n";
$resForma = $db->select("
    SELECT 
        v.cod_forma_liquidacion,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY v.cod_forma_liquidacion
    ORDER BY total_pend DESC
");
print_r($resForma);

echo "\n=== Group pending by cod_almacen and whether overdue (<= 2026-06-27) ===\n";
$resAlmOverdue = $db->select("
    SELECT 
        f.cod_almacen,
        CASE WHEN v.fecha_vencimiento <= '20260627' THEN 'Overdue' ELSE 'Future' END as status,
        COUNT(*) as cnt,
        SUM(v.importe - v.importe_cobrado) as total_pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura AND v.tipo_factura = f.tipo_factura AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY f.cod_almacen, CASE WHEN v.fecha_vencimiento <= '20260627' THEN 'Overdue' ELSE 'Future' END
    ORDER BY f.cod_almacen, status
");
print_r($resAlmOverdue);
