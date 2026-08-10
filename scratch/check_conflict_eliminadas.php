<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$perStart = "20250101 00:00:00";
$perEnd = "20250628 23:59:59";

// 1) Tabla de conflictos de replica de facturas_compras_cabecera
echo "=== MSmerge_conflict_Integral_facturas_compras_cabecera: columnas ===\n";
try {
    $cols = $erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('MSmerge_conflict_Integral_facturas_compras_cabecera') ORDER BY column_id");
    echo "  cols: ".implode(', ', array_map(fn($r)=>$r->name, $cols))."\n";
    $cnt = $erp->select("SELECT COUNT(*) c FROM MSmerge_conflict_Integral_facturas_compras_cabecera")[0]->c;
    echo "  rows total: $cnt\n";
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

echo "\n=== conflict_Integral_facturas_compras_cabecera 2025 (empresa=1) ===\n";
try {
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s FROM MSmerge_conflict_Integral_facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025") as $r)
    printf("  FULL 2025: count=%5d sum=%12.2f\n", $r->c, $r->s);
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s FROM MSmerge_conflict_Integral_facturas_compras_cabecera WHERE cod_empresa=1 AND fecha_factura>=? AND fecha_factura<=?", [$perStart,$perEnd]) as $r)
    printf("  PERIODO:   count=%5d sum=%12.2f\n", $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

echo "\n=== conflict 2026 (empresa=1) ===\n";
try {
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s FROM MSmerge_conflict_Integral_facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2026") as $r)
    printf("  FULL 2026: count=%5d sum=%12.2f\n", $r->c, $r->s);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// 2) sii_facturas_compras_eliminadas: columnas (con fecha?)
echo "\n=== sii_facturas_compras_eliminadas: columnas ===\n";
try {
    $cols = $erp->select("SELECT name FROM sys.columns WHERE object_id=object_id('sii_facturas_compras_eliminadas') ORDER BY column_id");
    echo "  cols: ".implode(', ', array_map(fn($r)=>$r->name, $cols))."\n";
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// 3) SUMA: cabecera(empresa=1) + conflict(empresa=1) -> da 6572 / 6170109.55?
echo "\n=== UNION cabecera + conflict 2025 empresa=1 (sumando) ===\n";
try {
foreach ($erp->select("SELECT COUNT(*) c, SUM(s) as sum FROM (SELECT importe_impuestos as s, cod_factura, cod_empresa FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025 UNION ALL SELECT importe_impuestos, cod_factura, cod_empresa FROM MSmerge_conflict_Integral_facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025) x") as $r)
    printf("  sumando ambos: count=%5d sum=%12.2f  (esp 6572 / 6170109.55)\n", $r->c, $r->sum);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// 4) Quizas el ERP cuenta facturas_compras (linking) distintas de cabecera
echo "\n=== facturas_compras (linking) 2025 empresa=1 totales (sin join) ===\n";
try {
foreach ($erp->select("SELECT COUNT(*) c FROM facturas_compras WHERE cod_empresa=1") as $r)
    printf("  rows facturas_compras empresa=1: %d\n", $r->c);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }