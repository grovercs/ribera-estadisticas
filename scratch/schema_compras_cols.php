<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== hist_compras_linea columnas ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('hist_compras_linea') ORDER BY column_id") as $r)
    printf("  %-26s %s\n", $r->name, $r->t);

echo "\n=== hist_compras_cabecera columnas (fecha, proveedor) ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('hist_compras_cabecera') AND (name LIKE '%fecha%' OR name LIKE '%proveedor%' OR name LIKE '%empresa%' OR name LIKE '%factura%' OR name LIKE '%tipo%') ORDER BY column_id") as $r)
    printf("  %-26s %s\n", $r->name, $r->t);

echo "\n=== articulos_proveedores columnas ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('articulos_proveedores') ORDER BY column_id") as $r)
    printf("  %-26s %s\n", $r->name, $r->t);

echo "\n=== stocks columnas ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('stocks') ORDER BY column_id") as $r)
    printf("  %-26s %s\n", $r->name, $r->t);

echo "\n=== movimiento_stock columnas (tipo movimiento) ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('movimiento_stock') AND (name LIKE '%tipo%' OR name LIKE '%fecha%' OR name LIKE '%cantidad%' OR name LIKE '%almacen%' OR name LIKE '%articulo%') ORDER BY column_id") as $r)
    echo "  $r->name\n";

echo "\n=== Cobertura: hist_compras_linea 2025-2026, count y avg precio ===\n";
$r = $erp->select("SELECT COUNT(*) c, COUNT(DISTINCT cod_articulo) arts, COUNT(DISTINCT cod_proveedor) provs FROM hist_compras_linea")[0];
printf("  lineas=%d articulos_distintos=%d proveedores_distintos=%d\n", $r->c, $r->arts, $r->provs);
$r = $erp->select("SELECT YEAR(c.fecha_compra) y, COUNT(*) c FROM hist_compras_linea l JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.cod_empresa=c.cod_empresa GROUP BY YEAR(c.fecha_compra) ORDER BY y DESC");
echo "  (si falla el join, probaremos otras claves)\n";