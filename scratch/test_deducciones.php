<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== SUM(importe_impuestos), SUM(deducciones), dto comercial, dto pronto pago por anyo ===\n";
foreach ($erp->select("SELECT YEAR(fecha_factura) y, COUNT(*) c,
    SUM(importe_impuestos) imp_iva,
    SUM(deducciones) ded,
    SUM(importe_dto_comercial) dto_com,
    SUM(importe_dto_pronto_pago) dto_pp
    FROM facturas_compras_cabecera
    WHERE fecha_factura IS NOT NULL
    GROUP BY YEAR(fecha_factura) ORDER BY y DESC") as $r) {
    printf("  y=%s count=%5d imp_iva=%12.2f deducc=%10.2f dto_com=%10.2f dto_pp=%10.2f  imp_iva-ded=%12.2f\n",
        $r->y, $r->c, $r->imp_iva, $r->ded, $r->dto_com, $r->dto_pp, $r->imp_iva - $r->ded);
}

echo "\nEsperado: 2026=2752955.49 (count 2715)  |  2025=6170109.55 (count 6572)\n";

// Vista que use el ERP?
echo "\n=== Vistas relacionadas con compras/facturas/gastos ===\n";
foreach ($erp->select("SELECT name FROM sys.views WHERE name LIKE '%compra%' OR name LIKE '%factura%' OR name LIKE '%gasto%' ORDER BY name") as $r)
    echo "  $r->name\n";