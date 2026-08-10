<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$perStart = "20250101 00:00:00";
$perEnd = "20250628 23:59:59";

function f($erp, $sql, $b, $label) {
    $r = $erp->select($sql, $b)[0];
    printf("  %-30s count=%5d sum=%12.2f\n", $label, $r->c, $r->s);
}

echo "=== 2026 con cod_empresa=1 ===\n";
f($erp, "SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2026 AND MONTH(fecha_factura)=MONTH(GETDATE())", [], "mes_actual (esp 236/294066.72)");
f($erp, "SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2026 AND MONTH(fecha_factura)=MONTH(DATEADD(MONTH,-1,GETDATE()))", [], "mes_anterior (esp 487/657575.31)");
f($erp, "SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2026", [], "year_actual (esp 2715/2752955.49)");

echo "\n=== 2025 con cod_empresa=1 ===\n";
f($erp, "SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND fecha_factura>=? AND fecha_factura<=?", [$perStart,$perEnd], "periodo (esp 3092/2778886.19)");
f($erp, "SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025", [], "year_anterior (esp 6572/6170109.55)");

echo "\n=== Split por empresa 2026 ===\n";
foreach ($erp->select("SELECT cod_empresa, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2026 GROUP BY cod_empresa ORDER BY cod_empresa") as $r)
    printf("  empresa=%s count=%5d sum=%12.2f\n", $r->cod_empresa, $r->c, $r->s);

echo "\n=== Split por empresa 2025-periodo ===\n";
foreach ($erp->select("SELECT cod_empresa, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE fecha_factura>=? AND fecha_factura<=? GROUP BY cod_empresa ORDER BY cod_empresa", [$perStart,$perEnd]) as $r)
    printf("  empresa=%s count=%5d sum=%12.2f\n", $r->cod_empresa, $r->c, $r->s);