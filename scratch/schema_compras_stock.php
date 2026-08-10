<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== Tablas relacionadas con compras/lineas/stock/articulos ===\n";
foreach ($erp->select("SELECT name FROM sys.tables WHERE name LIKE '%compra%' OR name LIKE '%linea%' OR name LIKE '%stock%' OR name LIKE '%articulo%' OR name LIKE '%inventari%' OR name LIKE '%existencia%' OR name LIKE '%precio%' OR name LIKE '%coste%' OR name LIKE '%proveedor%' ORDER BY name") as $r)
    echo "  $r->name\n";

echo "\n=== ¿Existe hist_compras_linea (detalle de linea de compra)? ===\n";
foreach ($erp->select("SELECT name FROM sys.tables WHERE name LIKE 'hist_compras%' OR name LIKE '%compras_linea%' OR name LIKE 'lineas_compras%' OR name LIKE 'linea_compras%'") as $r)
    echo "  $r->name\n";

echo "\n=== Columnas de hist_ventas_linea (precio_coste, precio, cantidad) ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('hist_ventas_linea') AND name IN ('precio_coste','precio','cantidad','cod_articulo','importe_impuestos','descripcion') ORDER BY column_id") as $r)
    printf("  %-22s %s\n", $r->name, $r->t);

echo "\n=== Columnas de articulos (coste, stock, precio) ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('articulos') AND (name LIKE '%coste%' OR name LIKE '%precio%' OR name LIKE '%stock%' OR name LIKE '%exist%' OR name LIKE '%peso%' OR name LIKE '%margen%' OR name LIKE '%iva%' OR name LIKE '%familia%' OR name LIKE '%proveedor%') ORDER BY column_id") as $r)
    printf("  %-22s %s\n", $r->name, $r->t);

echo "\n=== Tabla de stock / existencias ===\n";
foreach ($erp->select("SELECT name FROM sys.tables WHERE name LIKE '%stock%' OR name LIKE '%exist%' OR name LIKE '%inventari%' OR name LIKE '%almacen%art%'") as $r)
    echo "  $r->name\n";