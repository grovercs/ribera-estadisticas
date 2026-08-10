<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== vencimientos_facturas columnas ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('vencimientos_facturas') ORDER BY column_id") as $r)
    printf("  %-30s %s\n", $r->name, $r->t);

echo "\n=== devoluciones_vencimientos_ventas columnas ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('devoluciones_vencimientos_ventas') ORDER BY column_id") as $r)
    printf("  %-30s %s\n", $r->name, $r->t);

echo "\n=== vencimientos_facturas: total y pendiente (importe-importe_cobrado>0) ===\n";
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend FROM vencimientos_facturas v WHERE (v.importe - v.importe_cobrado) > 0")[0];
printf("  pendientes totales: count=%d  importe=%.2f\n", $r->c, $r->pend);

echo "\n=== Reproducir impagados actual (con devolucion) ===\n";
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM devoluciones_vencimientos_ventas d
    INNER JOIN vencimientos_facturas v ON d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero
    WHERE (v.importe - v.importe_cobrado) > 0")[0];
printf("  impagados (con devolucion): count=%d  importe=%.2f\n", $r->c, $r->pend);

echo "\n=== Distintos tipo_factura en vencimientos_facturas ===\n";
foreach ($erp->select("SELECT tipo_factura, COUNT(*) c FROM vencimientos_facturas GROUP BY tipo_factura ORDER BY c DESC") as $r)
    printf("  tipo_factura=%s  count=%d\n", $r->tipo_factura, $r->c);

echo "\n=== ¿Columnas tipo texto raras (ZIMP/ZJUZ...)? Buscar columnas con 'estado','tipo','clase','motivo','cod_' ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('vencimientos_facturas') AND (name LIKE '%tipo%' OR name LIKE '%estado%' OR name LIKE '%clase%' OR name LIKE '%motivo%' OR name LIKE '%cod_%' OR name LIKE '%status%') ORDER BY name") as $r)
    echo "  $r->name\n";