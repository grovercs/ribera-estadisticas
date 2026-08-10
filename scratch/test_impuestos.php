<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Buscar tabla impuestos_facturas_compras (no MSmerge)
echo "=== Tablas con 'impuestos' y 'compras' ===\n";
foreach ($erp->select("SELECT name FROM sys.tables WHERE name LIKE '%impuesto%compra%' OR name LIKE '%compra%impuesto%' OR name LIKE 'impuestos_facturas_compras%' ORDER BY name") as $r) echo "  $r->name\n";

// Probar impuestos_facturas_compras
$tbl = 'impuestos_facturas_compras';
echo "\n=== $tbl columnas ===\n";
try {
    $cols = $erp->select("SELECT name, type_name(system_type_id) t FROM sys.columns WHERE object_id=object_id('$tbl') ORDER BY column_id");
    foreach ($cols as $r) printf("  %-30s %s\n", $r->name, $r->t);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

echo "\n=== $tbl: count y sum por anyo (empresa=1) ===\n";
try {
foreach ($erp->select("SELECT YEAR(c.fecha_factura) y, COUNT(*) c, SUM(i.base_imponible + i.cuota_iva) as tot
    FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c
      ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa
    WHERE c.cod_empresa=1
    GROUP BY YEAR(c.fecha_factura) ORDER BY y DESC") as $r)
    printf("  y=%s count=%5d sum=%.2f\n", $r->y, $r->c, $r->tot);
} catch (\Exception $e) { echo "  ERR join: ".$e->getMessage()."\n"; }

// Probar alternativas: la tabla puede tener su propia fecha o solo claves
echo "\n=== $tbl sample TOP 3 ===\n";
try {
    foreach ($erp->select("SELECT TOP 3 * FROM impuestos_facturas_compras") as $r) print_r($r);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }