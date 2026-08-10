<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$year = 2026; $yearPrev = 2025;
$perStart = "{$yearPrev}0101 00:00:00";
$perEnd = "{$yearPrev}0628 23:59:59";
$esp = [
    'mes_actual'            => [236,  294066.72],
    'mes_anterior'          => [487,  657575.31],
    'year_actual'           => [2715, 2752955.49],
    'year_anterior_periodo' => [3092, 2778886.19],
    'year_anterior'         => [6572, 6170109.55],
];

function cmp($label, $sql, $params, $esp) {
    global $erp;
    $r = $erp->select($sql, $params)[0];
    $c = (int)$r->count; $s = (float)$r->importe;
    [$ec,$es] = $esp;
    printf("  %-22s count: esp=%5d obt=%5d %s | imp: esp=%11.2f obt=%11.2f diff=%+10.2f %s\n",
        $label, $ec, $c, $c===$ec?'OK':'XX', $es, $s, $s-$es, abs($s-$es)<1?'OK':'XX');
}

// fecha_contabilizacion
echo "=== facturas_compras_cabecera usando fecha_contabilizacion + importe_impuestos ===\n";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_contabilizacion)=? AND MONTH(fecha_contabilizacion)=MONTH(GETDATE())", [$year], $esp['mes_actual']);
cmp('mes_anterior', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_contabilizacion)=? AND MONTH(fecha_contabilizacion)=MONTH(DATEADD(MONTH,-1,GETDATE()))", [$year], $esp['mes_anterior']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_contabilizacion)=?", [$year], $esp['year_actual']);
cmp('year_anterior_periodo', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE fecha_contabilizacion>=? AND fecha_contabilizacion<=?", [$perStart,$perEnd], $esp['year_anterior_periodo']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_contabilizacion)=?", [$yearPrev], $esp['year_anterior']);

// Distribucion tipo_factura en mes actual
echo "\n=== tipo_factura en mes actual (2026-06, por fecha_factura) ===\n";
foreach ($erp->select("SELECT tipo_factura, COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=2026 AND MONTH(fecha_factura)=MONTH(GETDATE()) GROUP BY tipo_factura ORDER BY tipo_factura") as $r)
    printf("  tipo=%-8s count=%5d sum=%12.2f\n", $r->tipo_factura, $r->c, $r->s);

// Registros con fecha_factura NULL pero fecha_contabilizacion en mes actual
echo "\n=== fecha_factura NULL con fecha_contabilizacion en 2026-06 ===\n";
foreach ($erp->select("SELECT COUNT(*) c, SUM(importe_impuestos) s FROM facturas_compras_cabecera WHERE fecha_factura IS NULL AND YEAR(fecha_contabilizacion)=2026 AND MONTH(fecha_contabilizacion)=MONTH(GETDATE())") as $r)
    printf("  count=%d sum=%12.2f\n", $r->c, $r->s);