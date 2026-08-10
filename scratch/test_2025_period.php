<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$perStart = "20250101 00:00:00";
$perEnd = "20250628 23:59:59";

echo "=== 2025 PERIODO (01-01..06-28) con importe_impuestos + fecha_factura ===\n";
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE fecha_factura>=? AND fecha_factura<=?", [$perStart,$perEnd]) as $r)
    printf("  count=%4d sum=%12.2f  (esperado 3092 / 2778886.19)\n", $r->c, $r->s);

echo "\n=== 2025 FULL con importe_impuestos + fecha_factura ===\n";
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2025") as $r)
    printf("  count=%4d sum=%12.2f  (esperado 6572 / 6170109.55)\n", $r->c, $r->s);

// facturas_compras (linking) count por anyo via hist_compras_cabecera
echo "\n=== facturas_compras (linking) join hist_compras_cabecera por anyo (tipo_compra=1) ===\n";
try {
foreach ($erp->select("SELECT YEAR(h.fecha_compra) y, COUNT(*) c FROM facturas_compras f JOIN hist_compras_cabecera h ON f.cod_compra=h.cod_compra AND f.cod_empresa=h.cod_empresa WHERE h.tipo_compra=1 AND h.fecha_compra IS NOT NULL GROUP BY YEAR(h.fecha_compra) ORDER BY y DESC") as $r)
    printf("  y=%s count=%5d\n", $r->y, $r->c);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// facturas_compras (linking) todas por anyo
echo "\n=== facturas_compras (linking) join hist por anyo (TODOS tipos) ===\n";
try {
foreach ($erp->select("SELECT YEAR(h.fecha_compra) y, h.tipo_compra tc, COUNT(*) c FROM facturas_compras f JOIN hist_compras_cabecera h ON f.cod_compra=h.cod_compra AND f.cod_empresa=h.cod_empresa WHERE h.fecha_compra IS NOT NULL GROUP BY YEAR(h.fecha_compra), h.tipo_compra ORDER BY y DESC, tc") as $r)
    printf("  y=%s tipo=%s count=%5d\n", $r->y, $r->tc, $r->c);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// Diferencia: cuantas facturas_compras_cabecera hay por cod_empresa en 2025
echo "\n=== facturas_compras_cabecera 2025 por cod_empresa ===\n";
foreach ($erp->select("SELECT cod_empresa, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2025 GROUP BY cod_empresa ORDER BY cod_empresa") as $r)
    printf("  empresa=%s count=%5d sum=%12.2f\n", $r->cod_empresa, $r->c, $r->s);