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

// Condicion: fecha_factura en periodo O (fecha_factura fuera y fecha_contabilizacion en periodo)
function cond($fechaCol, $y, $mo = null) {
    if ($mo !== null) {
        return "(YEAR($fechaCol)=$y AND MONTH($fechaCol)=$mo)";
    }
    return "YEAR($fechaCol)=$y";
}
function condRange($fechaCol, $s, $e) {
    return "($fechaCol >= '$s' AND $fechaCol <= '$e')";
}

function cmp($label, $sql, $params, $esp) {
    global $erp;
    try {
        $r = $erp->select($sql)[0];
        $c = (int)$r->count; $s = (float)$r->importe;
        [$ec,$es] = $esp;
        printf("  %-22s count: esp=%5d obt=%5d %s | imp: esp=%11.2f obt=%11.2f diff=%+10.2f %s\n",
            $label, $ec, $c, $c===$ec?'OK':'XX', $es, $s, $s-$es, abs($s-$es)<1?'OK':'XX');
    } catch (\Exception $e) { echo "  $label ERR: ".$e->getMessage()."\n"; }
}

// Solo fecha_factura + importe_impuestos (referencia)
echo "=== A) fecha_factura + importe_impuestos (referencia) ===\n";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE ".cond('fecha_factura',$year,'MONTH(GETDATE())'), [], $esp['mes_actual']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE ".cond('fecha_factura',$year), [], $esp['year_actual']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE ".cond('fecha_factura',$yearPrev), [], $esp['year_anterior']);

// Union: fecha_factura en periodo O fecha_contabilizacion en periodo (y fecha_factura fuera)
echo "\n=== B) (fecha_factura en periodo) O (fecha_contabilizacion en periodo) ===\n";
$mo = "MONTH(GETDATE())";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE (YEAR(fecha_factura)=$year AND MONTH(fecha_factura)=$mo) OR (YEAR(fecha_contabilizacion)=$year AND MONTH(fecha_contabilizacion)=$mo AND NOT (YEAR(fecha_factura)=$year AND MONTH(fecha_factura)=$mo))", [], $esp['mes_actual']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=$year OR (YEAR(fecha_contabilizacion)=$year AND YEAR(fecha_factura)<>$year)", [], $esp['year_actual']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=$yearPrev OR (YEAR(fecha_contabilizacion)=$yearPrev AND YEAR(fecha_factura)<>$yearPrev)", [], $esp['year_anterior']);

// C) Solo por fecha_contabilizacion + importe_impuestos
echo "\n=== C) fecha_contabilizacion + importe_impuestos ===\n";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE ".cond('fecha_contabilizacion',$year,'MONTH(GETDATE())'), [], $esp['mes_actual']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe_impuestos) importe FROM facturas_compras_cabecera WHERE ".cond('fecha_contabilizacion',$yearPrev), [], $esp['year_anterior']);