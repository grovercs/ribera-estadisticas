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
    'mes_actual'            => [236,   294066.72],
    'mes_anterior'          => [487,   657575.31],
    'year_actual'           => [2715,  2752955.49],
    'year_anterior_periodo' => [3092,  2778886.19],
    'year_anterior'         => [6572,  6170109.55],
];

function cmp($label, $sql, $params, $esp) {
    global $erp;
    $r = $erp->select($sql, $params)[0];
    $c = (int)$r->count; $s = (float)$r->importe;
    [$ec,$es] = $esp;
    $okc = $c===$ec; $oks = abs($s-$es)<1;
    printf("  %-22s count: esp=%5d obt=%5d %s | importe: esp=%12.2f obt=%12.2f diff=%+10.2f %s\n",
        $label, $ec, $c, $okc?'OK':'XX', $es, $s, $s-$es, $oks?'OK':'XX');
}

echo "=== ACTUAL: hist_compras_cabecera, tipo_compra=1 ===\n";
cmp('mes_actual', "SELECT COUNT(*) count, SUM(importe) importe FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=? AND MONTH(fecha_compra)=MONTH(GETDATE()) AND tipo_compra=1", [$year], $esp['mes_actual']);
cmp('mes_anterior', "SELECT COUNT(*) count, SUM(importe) importe FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=? AND MONTH(fecha_compra)=MONTH(DATEADD(MONTH,-1,GETDATE())) AND tipo_compra=1", [$year], $esp['mes_anterior']);
cmp('year_actual', "SELECT COUNT(*) count, SUM(importe) importe FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=? AND tipo_compra=1", [$year], $esp['year_actual']);
cmp('year_anterior_periodo', "SELECT COUNT(*) count, SUM(importe) importe FROM hist_compras_cabecera WHERE fecha_compra>=? AND fecha_compra<=? AND tipo_compra=1", [$perStart,$perEnd], $esp['year_anterior_periodo']);
cmp('year_anterior', "SELECT COUNT(*) count, SUM(importe) importe FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=? AND tipo_compra=1", [$yearPrev], $esp['year_anterior']);

// Distribucion por tipo_compra en mes actual y año actual para ver si otro tipo encaja
echo "\n=== Desglose por tipo_compra (mes actual 2026-06) ===\n";
foreach($erp->select("SELECT tipo_compra, COUNT(*) c, SUM(importe) s FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=2026 AND MONTH(fecha_compra)=MONTH(GETDATE()) GROUP BY tipo_compra ORDER BY tipo_compra") as $r)
    printf("  tipo=%s count=%5d sum=%12.2f\n",$r->tipo_compra,$r->c,$r->s);
echo "\n=== Desglose por tipo_compra (año actual 2026) ===\n";
foreach($erp->select("SELECT tipo_compra, COUNT(*) c, SUM(importe) s FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=2026 GROUP BY tipo_compra ORDER BY tipo_compra") as $r)
    printf("  tipo=%s count=%5d sum=%12.2f\n",$r->tipo_compra,$r->c,$r->s);
echo "\n=== Desglose por tipo_compra (año anterior 2025) ===\n";
foreach($erp->select("SELECT tipo_compra, COUNT(*) c, SUM(importe) s FROM hist_compras_cabecera WHERE YEAR(fecha_compra)=2025 GROUP BY tipo_compra ORDER BY tipo_compra") as $r)
    printf("  tipo=%s count=%5d sum=%12.2f\n",$r->tipo_compra,$r->c,$r->s);