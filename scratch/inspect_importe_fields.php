<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== TOP 8 facturas_compras_cabecera (mes actual 2026-06) ===\n";
foreach ($erp->select("SELECT TOP 8 cod_factura, fecha_factura, importe, importe_impuestos, importe_divisa, importe_divisa_impuestos FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2026 AND MONTH(fecha_factura)=MONTH(GETDATE()) ORDER BY importe DESC") as $r) {
    printf("  %-14s %s  imp=%10.2f imp_iva=%10.2f  div=%10.2f div_iva=%10.2f\n",
        $r->cod_factura, substr($r->fecha_factura,0,10), $r->importe, $r->importes_impuestos ?? $r->importe_impuestos, $r->importe_divisa, $r->importe_divisa_impuestos);
}

echo "\n=== Mes actual 2026-06: SUMs separados ===\n";
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe) s_imp, SUM(importe_impuestos) s_iva, SUM(importe_divisa) s_div, SUM(importe_divisa_impuestos) s_diviva FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2026 AND MONTH(fecha_factura)=MONTH(GETDATE())") as $r)
    printf("  count=%d  importe=%11.2f  importe_impuestos=%11.2f  divisa=%11.2f  divisa_impuestos=%11.2f\n", $r->c, $r->s_imp, $r->s_iva, $r->s_div, $r->s_diviva);

echo "\nEsperado mes actual: count=236 importe=294066.72\n";