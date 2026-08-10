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

echo "=== Columnas de facturas_compras_cabecera ===\n";
foreach ($erp->select("SELECT name, type_name(system_type_id) AS t FROM sys.columns WHERE object_id = object_id('facturas_compras_cabecera') ORDER BY column_id") as $r)
    printf("  %-30s %s\n", $r->name, $r->t);

function cmp($label, $sql, $params, $esp) {
    global $erp;
    try {
        $r = $erp->select($sql, $params)[0];
        $c = (int)$r->count; $s = (float)$r->importe;
        [$ec,$es] = $esp;
        printf("  %-22s count: esp=%5d obt=%5d %s | imp: esp=%11.2f obt=%11.2f diff=%+10.2f %s\n",
            $label, $ec, $c, $c===$ec?'OK':'XX', $es, $s, $s-$es, abs($s-$es)<1?'OK':'XX');
    } catch (\Exception $e) { echo "  $label ERROR: ".$e->getMessage()."\n"; }
}

echo "\n=== facturas_compras_cabecera (sin filtro tipo) ===\n";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=? AND MONTH(fecha_factura)=MONTH(GETDATE())", [$year], $esp['mes_actual']);
cmp('mes_anterior', "SELECT COUNT(*) count, SUM(importe) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=? AND MONTH(fecha_factura)=MONTH(DATEADD(MONTH,-1,GETDATE()))", [$year], $esp['mes_anterior']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=?", [$year], $esp['year_actual']);
cmp('year_anterior_periodo', "SELECT COUNT(*) count, SUM(importe) importe FROM facturas_compras_cabecera WHERE fecha_factura>=? AND fecha_factura<=?", [$perStart,$perEnd], $esp['year_anterior_periodo']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe) importe FROM facturas_compras_cabecera WHERE YEAR(fecha_factura)=?", [$yearPrev], $esp['year_anterior']);

// Buscar columnas fecha/importe/tipo reales
echo "\n=== Buscar columnas fecha*/tipo*/importe en facturas_compras_cabecera ===\n";
foreach ($erp->select("SELECT name FROM sys.columns WHERE object_id = object_id('facturas_compras_cabecera') AND (name LIKE '%fecha%' OR name LIKE '%tipo%' OR name LIKE '%importe%' OR name LIKE '%factura%') ORDER BY name") as $r)
    echo "  $r->name\n";

// Total general para ver escala
echo "\n=== Totales facturas_compras_cabecera por anyo ===\n";
foreach ($erp->select("SELECT YEAR(fecha_factura) y, COUNT(*) c, SUM(importe) s FROM facturas_compras_cabecera WHERE fecha_factura IS NOT NULL GROUP BY YEAR(fecha_factura) ORDER BY y DESC") as $r)
    printf("  anyo=%s count=%5d sum=%14.2f\n", $r->y, $r->c, $r->s);