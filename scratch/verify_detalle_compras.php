<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$year = 2026;
$refDate = new DateTime();
$yearPrev = $year - 1;
$yearAntPeriodoStart = "{$yearPrev}0101 00:00:00";
$yearAntPeriodoEnd = "{$yearPrev}" . $refDate->format('md 23:59:59');

$baseFrom = "FROM impuestos_facturas_compras i
    JOIN facturas_compras_cabecera c
      ON i.cod_factura = c.cod_factura AND i.cod_empresa = c.cod_empresa AND i.cod_proveedor = c.cod_proveedor
    LEFT JOIN proveedores pr ON i.cod_proveedor = pr.cod_proveedor
    WHERE c.cod_empresa = 1";

$cases = [
    'mes_actual'            => ["AND YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = MONTH(GETDATE())", [$year], "236/294066.72"],
    'mes_anterior'          => ["AND YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = MONTH(DATEADD(MONTH, -1, GETDATE()))", [$year], "487/657575.31"],
    'year_actual'           => ["AND YEAR(c.fecha_factura) = ?", [$year], "2715/2752955.49"],
    'year_anterior_periodo' => ["AND c.fecha_factura >= ? AND c.fecha_factura <= ?", [$yearAntPeriodoStart, $yearAntPeriodoEnd], "3094/2779452.36"],
    'year_anterior'         => ["AND YEAR(c.fecha_factura) = ?", [$yearPrev], "6572/6170109.55"],
];

foreach ($cases as $key => [$where, $bindings, $exp]) {
    $sql = "SELECT COUNT(*) c, SUM(i.importe) s {$baseFrom} {$where}";
    $r = $erp->select($sql, $bindings)[0];
    printf("  %-24s count=%5d sum=%12.2f  (esp %s)\n", $key, $r->c, $r->s, $exp);
}

// Probe the detail query for mes_actual to ensure proveedores join + columns work
echo "\n=== Detail probe (mes_actual, TOP 3) ===\n";
$rows = $erp->select("SELECT TOP 3 i.cod_factura, i.cod_proveedor, pr.razon_social, pr.cif,
    c.fecha_factura, i.cod_impuesto, i.porcentaje, i.base, i.importe_porcentaje cuota, i.importe total
    {$baseFrom} AND YEAR(c.fecha_factura)=? AND MONTH(c.fecha_factura)=MONTH(GETDATE())
    ORDER BY c.fecha_factura DESC, i.cod_factura, i.cod_impuesto", [$year]);
foreach ($rows as $r) {
    printf("  fac=%-12s prov=%-6s %-26s cif=%-10s %s imp=%s porc=%s base=%9.2f cuota=%7.2f tot=%9.2f\n",
        $r->cod_factura, $r->cod_proveedor, substr(trim($r->razon_social??''),0,26), trim($r->cif??''),
        $r->fecha_factura, $r->cod_impuesto, $r->porcentaje, (float)$r->base, (float)$r->cuota, (float)$r->total);
}