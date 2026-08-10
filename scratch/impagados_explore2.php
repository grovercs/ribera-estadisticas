<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Reproducir 22/11416.01 (impagados solo almacenes 1 y 2)
echo "=== Impagados actuales solo cod_almacen 1+2 ===\n";
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM devoluciones_vencimientos_ventas d
    INNER JOIN vencimientos_facturas v ON d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen IN (1,2)")[0];
printf("  count=%d importe=%.2f\n", $r->c, $r->pend);

echo "\n=== Distintos cod_forma_liquidacion (pendientes) ===\n";
foreach ($erp->select("SELECT cod_forma_liquidacion, COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM vencimientos_facturas v WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY cod_forma_liquidacion ORDER BY c DESC") as $r)
    printf("  cod_forma_liq=%-12s count=%5d pend=%12.2f\n", $r->cod_forma_liquidacion ?? 'NULL', $r->c, $r->pend);

echo "\n=== Distintos codigo_contable (pendientes) ===\n";
foreach ($erp->select("SELECT codigo_contable, COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM vencimientos_facturas v WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY codigo_contable ORDER BY c DESC") as $r)
    printf("  codigo_contable=%-12s count=%5d pend=%12.2f\n", $r->codigo_contable ?? 'NULL', $r->c, $r->pend);

echo "\n=== Pendientes: vencido vs no vencido (corte GETDATE) ===\n";
foreach ($erp->select("SELECT CASE WHEN v.fecha_vencimiento < GETDATE() THEN 'VENC' ELSE 'NOVEN' END grp, COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM vencimientos_facturas v WHERE (v.importe - v.importe_cobrado) > 0
    GROUP BY CASE WHEN v.fecha_vencimiento < GETDATE() THEN 'VENC' ELSE 'NOVEN' END ORDER BY grp") as $r)
    printf("  %s count=%5d pend=%12.2f\n", $r->grp, $r->c, $r->pend);

echo "\n=== fecha_devolucion NOT NULL (impagados reales devueltos por banco) ===\n";
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM vencimientos_facturas v WHERE (v.importe - v.importe_cobrado) > 0 AND v.fecha_devolucion IS NOT NULL")[0];
printf("  con fecha_devolucion: count=%d importe=%.2f\n", $r->c, $r->pend);

echo "\n=== Pendientes solo almacenes 1+2 (match vista pnd) ===\n";
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe - v.importe_cobrado) pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0 AND f.cod_almacen IN (1,2)
      AND NOT EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero)")[0];
printf("  pendientes 1+2: count=%d importe=%.2f\n", $r->c, $r->pend);

echo "\n=== Muestra cod_forma_liquidacion que empiezan por Z ===\n";
foreach ($erp->select("SELECT DISTINCT cod_forma_liquidacion FROM vencimientos_facturas WHERE cod_forma_liquidacion LIKE 'Z%'") as $r)
    echo "  ".$r->cod_forma_liquidacion."\n";