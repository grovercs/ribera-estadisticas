<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(__DIR__ . '/erp_compras_2025.csv'));
$lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');

function normFac($s) {
    $s = trim($s);
    if ($s === '') return '';
    if (ctype_digit($s)) return ltrim($s, '0');
    return $s;
}
function normFecha($s) {
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($s), $m))
        return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    return trim($s);
}

$csv = []; $bad = 0;
foreach ($lines as $lin) {
    $p = array_map(fn($x) => trim($x), explode(';', $lin));
    if (count($p) < 8) { $bad++; continue; }
    // col0 empty, col1=cod_factura, col2=fecha, col3=cod_prov, col4=razon, col5=base, col6=iva, col7=total
    $fac = $p[1]; $fecha = normFecha($p[2]); $prov = $p[3]; $razon = $p[4];
    $base = (float)str_replace(',', '.', $p[5]);
    $iva = (float)str_replace(',', '.', $p[6]);
    $total = (float)str_replace(',', '.', $p[7]);
    $key = $prov . '|' . normFac($fac);
    $csv[$key] = ['fac' => $fac, 'fecha' => $fecha, 'prov' => $prov, 'razon' => $razon, 'base' => $base, 'iva' => $iva, 'total' => $total, 'totbi' => $base + $iva];
}
echo "CSV filas OK: " . count($csv) . " | bad: $bad\n";
printf("CSV SUM base=%.2f iva=%.2f base+iva=%.2f  (ERP total 6170109.55)\n",
    array_sum(array_column($csv, 'base')), array_sum(array_column($csv, 'iva')), array_sum(array_column($csv, 'totbi')));

// cabecera empresa=1 2025
$dbRows = $erp->select("SELECT cod_factura, cod_proveedor, CONVERT(date, fecha_factura) as f, importe, importe_impuestos
    FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025");
$db = [];
foreach ($dbRows as $r) {
    $key = $r->cod_proveedor . '|' . normFac($r->cod_factura);
    $db[$key] = ['fecha' => $r->f, 'imp' => (float)$r->importe, 'impIV' => (float)$r->importe_impuestos, 'fac' => $r->cod_factura];
}
echo "Cabecera empresa=1 2025: " . count($db) . "\n";

$onlyCsv = [];
foreach ($csv as $k => $v) if (!isset($db[$k])) $onlyCsv[$k] = $v;
$onlyDb = [];
foreach ($db as $k => $v) if (!isset($csv[$k])) $onlyDb[$k] = $v;

echo "\n=== EN CSV pero NO en cabecera: " . count($onlyCsv) . " ===\n";
$sumB = 0; $sumI = 0;
foreach ($onlyCsv as $v) {
    printf("  prov=%-6s fac=%-14s %s base=%9.2f iva=%8.2f tot=%9.2f  %s\n", $v['prov'], $v['fac'], $v['fecha'], $v['base'], $v['iva'], $v['total'], substr($v['razon'],0,28));
    $sumB += $v['base']; $sumI += $v['iva'];
}
printf("  --- SUM base=%.2f iva=%.2f base+iva=%.2f\n", $sumB, $sumI, $sumB + $sumI);

echo "\n=== EN cabecera pero NO en CSV: " . count($onlyDb) . " ===\n";
$cnt = 0;
foreach ($onlyDb as $k => $v) {
    if ($cnt++ >= 40) { echo "  ... (mas " . (count($onlyDb) - 40) . ")\n"; break; }
    printf("  key=%-22s %s imp=%9.2f impIV=%9.2f\n", $k, $v['fecha'], $v['imp'], $v['impIV']);
}