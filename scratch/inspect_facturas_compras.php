<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== Columnas de facturas_compras (no cabecera) ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) AS t FROM sys.columns WHERE object_id = object_id('facturas_compras') ORDER BY column_id") as $r)
    printf("  %-28s %s\n", $r->name, $r->t);

echo "\n=== facturas_compras: conteo/sum por anyo (si tiene fecha_factura/importe_impuestos) ===\n";
try {
    foreach ($erp->select("SELECT YEAR(fecha_factura) y, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras WHERE fecha_factura IS NOT NULL GROUP BY YEAR(fecha_factura) ORDER BY y DESC") as $r)
        printf("  anyo=%s count=%5d sum=%12.2f\n", $r->y, $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// Cuantas facturas_compras_cabecera tienen importe_impuestos NULL o 0 en 2026
echo "\n=== facturas_compras_cabecera 2026 con importe_impuestos NULL o 0 ===\n";
foreach ($erp->select("SELECT COUNT(*) c FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2026 AND (importe_impuestos IS NULL OR importe_impuestos=0)") as $r)
    printf("  count=%d\n", $r->c);

// Buscar tablas de gastos
echo "\n=== Tablas con 'gasto' o 'cuenta_corriente' o 'cc' ===\n";
foreach ($erp->select("SELECT name FROM sys.tables WHERE name LIKE '%gasto%' OR name LIKE '%cuenta%' OR name LIKE '%auto%' ORDER BY name") as $r)
    echo "  $r->name\n";