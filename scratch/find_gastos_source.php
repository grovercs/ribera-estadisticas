<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// 1) Tablas con 'gasto' en el nombre
echo "=== Tablas con 'gasto' ===\n";
foreach ($erp->select("SELECT name FROM sys.tables WHERE name LIKE '%gasto%' ORDER BY name") as $r) echo "  $r->name\n";

// 2) facturas_ventas_cabecera: autofacturas / gastos por anyo (empresa=1)
echo "\n=== facturas_ventas_cabecera: columnas tipo/autofactura ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('facturas_ventas_cabecera') AND (name LIKE '%auto%' OR name LIKE '%tipo%' OR name LIKE '%gasto%' OR name LIKE '%rect%') ORDER BY column_id") as $r) echo "  $r->name\n";

// 3) facturas_compras_cabecera: autofactura flag distribution 2025
echo "\n=== facturas_compras_cabecera.autofactura 2025 (empresa=1) ===\n";
try {
foreach ($erp->select("SELECT autofactura, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025 GROUP BY autofactura") as $r)
    printf("  autofactura=[%s] count=%5d sum=%12.2f\n", $r->autofactura, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// 4) tipo_factura distribution 2025 empresa=1
echo "\n=== facturas_compras_cabecera.tipo_factura 2025 (empresa=1) ===\n";
try {
foreach ($erp->select("SELECT tipo_factura, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025 GROUP BY tipo_factura ORDER BY tipo_factura") as $r)
    printf("  tipo_factura=[%s] count=%5d sum=%12.2f\n", $r->tipo_factura, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// 5) facturas_compra_cc_cabecera: vinculo con cabecera - cuantos cc NO estan en cabecera por anyo
echo "\n=== facturas_compra_cc_cabecera: sample y vinculo ===\n";
try {
    $cols = $erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('facturas_compra_cc_cabecera') ORDER BY column_id");
    echo "  cols: ".implode(', ', array_map(fn($r)=>$r->name, $cols))."\n";
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// 6) Buscar tablas que tengan fecha_factura Y no sean ventas/compras_cabecera ya vistas
echo "\n=== Otras tablas con fecha_factura ===\n";
foreach ($erp->select("SELECT t.name FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id WHERE c.name='fecha_factura' ORDER BY t.name") as $r) echo "  $r->name\n";

// 7) Comprobar si hist_compras_cabecera tiene tipo_compra=0 u otros en 2025 (gastos)
echo "\n=== hist_compras_cabecera 2025 por tipo_compra (empresa=1) ===\n";
try {
foreach ($erp->select("SELECT tipo_compra, COUNT(*) c, SUM(importe) s FROM hist_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_compra)=2025 GROUP BY tipo_compra ORDER BY tipo_compra") as $r)
    printf("  tipo=%s count=%5d sum=%12.2f\n", $r->tipo_compra, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }