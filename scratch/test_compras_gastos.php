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
    try {
        $r = $erp->select($sql, $params)[0];
        $c = (int)$r->count; $s = (float)$r->importe;
        [$ec,$es] = $esp;
        printf("  %-22s count: esp=%5d obt=%5d %s | imp: esp=%11.2f obt=%11.2f diff=%+10.2f %s\n",
            $label, $ec, $c, $c===$ec?'OK':'XX', $es, $s, $s-$es, abs($s-$es)<1?'OK':'XX');
    } catch (\Exception $e) { echo "  $label ERR: ".$e->getMessage()."\n"; }
}

echo "=== facturas_compras_cabecera: importe + importe_impuestos (bruto con IVA) ===\n";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=? AND MONTH(fecha_factura)=MONTH(GETDATE())", [$year], $esp['mes_actual']);
cmp('mes_anterior', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=? AND MONTH(fecha_factura)=MONTH(DATEADD(MONTH,-1,GETDATE()))", [$year], $esp['mes_anterior']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=?", [$year], $esp['year_actual']);
cmp('year_anterior_periodo', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe FROM facturas_compras_cabecera WHERE fecha_factura>=? AND fecha_factura<=?", [$perStart,$perEnd], $esp['year_anterior_periodo']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=?", [$yearPrev], $esp['year_anterior']);

echo "\n=== Columnas fecha/importe de facturas_compra_cc_cabecera ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id = object_id('facturas_compra_cc_cabecera') AND (name LIKE '%fecha%' OR name LIKE '%importe%') ORDER BY name") as $r)
    echo "  $r->name\n";

// Aportacion de facturas_compra_cc_cabecera por anyo (gastos)
echo "\n=== facturas_compra_cc_cabecera por anyo (posibles gastos) ===\n";
try {
foreach ($erp->select("SELECT YEAR(fecha_factura) y, COUNT(*) c, SUM(importe) s, SUM(importe_impuestos) si FROM facturas_compra_cc_cabecera WHERE fecha_factura IS NOT NULL GROUP BY YEAR(fecha_factura) ORDER BY y DESC") as $r)
    printf("  anyo=%s count=%5d imp=%11.2f imp+iva=%11.2f\n", $r->y, $r->c, $r->s, $r->s+$r->si);
} catch (\Exception $e) { echo "  ERR: ".$e->getMessage()."\n"; }

// Combinado: facturas_compras_cabecera + facturas_compra_cc_cabecera (bruto)
echo "\n=== COMBINADO compras + cc (bruto importe+impuestos) ===\n";
$union = "FROM (SELECT importe, importe_impuestos, fecha_factura FROM facturas_compras_cabecera
          UNION ALL SELECT importe, importe_impuestos, fecha_factura FROM facturas_compra_cc_cabecera) x";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe $union WHERE YEAR(fecha_factura)=? AND MONTH(fecha_factura)=MONTH(GETDATE())", [$year], $esp['mes_actual']);
cmp('mes_anterior', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe $union WHERE YEAR(fecha_factura)=? AND MONTH(fecha_factura)=MONTH(DATEADD(MONTH,-1,GETDATE()))", [$year], $esp['mes_anterior']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe $union WHERE YEAR(fecha_factura)=?", [$year], $esp['year_actual']);
cmp('year_anterior_periodo', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe $union WHERE fecha_factura>=? AND fecha_factura<=?", [$perStart,$perEnd], $esp['year_anterior_periodo']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe+importe_impuestos) importe $union WHERE YEAR(fecha_factura)=?", [$yearPrev], $esp['year_anterior']);