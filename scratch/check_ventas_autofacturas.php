<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Columnas de ventas relevantes
echo "=== facturas_ventas_cabecera: cols (auto/tipo/gasto/rect) ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('facturas_ventas_cabecera') AND (name LIKE '%auto%' OR name LIKE '%tipo%' OR name LIKE '%gasto%' OR name LIKE '%rect%' OR name LIKE '%compra%') ORDER BY column_id") as $r) echo "  $r->name\n";

// tipo_factura en ventas 2025 empresa=1
echo "\n=== facturas_ventas_cabecera 2025 empresa=1 por tipo_factura ===\n";
try {
foreach ($erp->select("SELECT tipo_factura, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_ventas_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025 GROUP BY tipo_factura ORDER BY tipo_factura") as $r)
    printf("  tipo=[%s] count=%5d sum=%12.2f\n", $r->tipo_factura, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// ventas con autofactura flag (buscar columna autofactura)
echo "\n=== existe columna autofactura en ventas? ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('facturas_ventas_cabecera') AND name LIKE '%auto%'") as $r) echo "  $r->name\n";

// Comprobar: union de compras_cabecera(empresa=1) + ventas autofacturas podria dar 6572?
// Buscar en facturas_compras_cabecera columna autofactura valores
echo "\n=== facturas_compras_cabecera: todas las cols con auto/tipo/rect/gasto ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('facturas_compras_cabecera') AND (name LIKE '%auto%' OR name LIKE '%tipo%' OR name LIKE '%gasto%' OR name LIKE '%rect%') ORDER BY column_id") as $r) echo "  $r->name\n";

// Diferencia 2026 periodo actual (mes 06): 233 vs 236. 3 registros de mas cuenta el ERP.
// Verificar si hay facturas_ventas que podrian ser esos 3
echo "\n=== facturas_ventas_cabecera 2026-06 empresa=1 por tipo ===\n";
try {
foreach ($erp->select("SELECT tipo_factura, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_ventas_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2026 AND MONTH(fecha_factura)=MONTH(GETDATE()) GROUP BY tipo_factura ORDER BY tipo_factura") as $r)
    printf("  tipo=[%s] count=%5d sum=%12.2f\n", $r->tipo_factura, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// Buscar vistas/proc que mencionen 'gastos' o 'compras y gastos'
echo "\n=== Vistas con 'compra' o 'gasto' o 'factura' ===\n";
foreach ($erp->select("SELECT name FROM sys.views WHERE name LIKE '%compra%' OR name LIKE '%gasto%' OR name LIKE '%factura%' ORDER BY name") as $r) echo "  $r->name\n";