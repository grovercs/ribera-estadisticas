<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(__DIR__ . '/erp_compras_2025.csv'));
$lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');

function normFecha($s) {
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($s), $m))
        return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    return trim($s);
}

// ERP rows como lista (multiset)
$erpRows = [];
$totBase = 0; $totIva = 0;
foreach ($lines as $lin) {
    $p = array_map(fn($x) => trim($x), explode(';', $lin));
    if (count($p) < 8) continue;
    if ($p[2] === '' || $p[3] === '') continue; // skip blanks
    $fecha = normFecha($p[2]);
    $prov = $p[3];
    $base = (float)str_replace(',', '.', $p[5]);
    $iva = (float)str_replace(',', '.', $p[6]);
    $tot = $base + $iva;
    $erpRows[] = ['prov' => $prov, 'fecha' => $fecha, 'tot' => round($tot, 2), 'fac' => $p[1], 'razon' => $p[4]];
    $totBase += $base; $totIva += $iva;
}
echo "ERP filas: " . count($erpRows) . "\n";
printf("ERP SUM base+iva = %.2f  (ERP esperado 6170109.55)\n", $totBase + $totIva);

// cabecera como lista (multiset)
$dbRows = $erp->select("SELECT cod_proveedor, CONVERT(date, fecha_factura) as f, importe, importe_impuestos
    FROM facturas_compras_cabecera WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025");
$dbList = [];
$dbSum = 0;
foreach ($dbRows as $r) {
    $dbList[] = ['prov' => $r->cod_proveedor, 'fecha' => $r->f, 'tot' => round((float)$r->importe_impuestos, 2)];
    $dbSum += (float)$r->importe_impuestos;
}
echo "Cabecera filas: " . count($dbList) . "\n";
printf("Cabecera SUM imp_impuestos = %.2f\n\n", $dbSum);

// Multiset diff por (prov, fecha, tot)
function mkey($r) { return $r['prov'] . '|' . $r['fecha'] . '|' . $r['tot']; }
$erpKeys = []; foreach ($erpRows as $r) { $k = mkey($r); $erpKeys[$k] = ($erpKeys[$k] ?? 0) + 1; }
$dbKeys = []; foreach ($dbList as $r) { $k = mkey($r); $dbKeys[$k] = ($dbKeys[$k] ?? 0) + 1; }

// ERP - cabecera (facturas que el ERP tiene y la cabecera no)
$allKeys = array_unique(array_merge(array_keys($erpKeys), array_keys($dbKeys)));
$missing = []; // en ERP pero no en cabecera
$extra = [];   // en cabecera pero no en ERP
$missSum = 0; $extraSum = 0;
foreach ($allKeys as $k) {
    $e = $erpKeys[$k] ?? 0;
    $d = $dbKeys[$k] ?? 0;
    if ($e > $d) { $missing[$k] = $e - $d; $parts = explode('|', $k); $missSum += $parts[2] * ($e - $d); }
    if ($d > $e) { $extra[$k] = $d - $e; $parts = explode('|', $k); $extraSum += $parts[2] * ($d - $e); }
}
$missCount = array_sum($missing); $extraCount = array_sum($extra);
echo "=== ERP tiene, cabecera NO: $missCount facturas, sumatoria = " . number_format($missSum, 2) . " ===\n";
foreach ($missing as $k => $n) {
    // buscar la fila ERP original
    $parts = explode('|', $k);
    $match = null;
    foreach ($erpRows as $r) if (mkey($r) === $k) { $match = $r; break; }
    printf("  x%-2d prov=%-6s %s tot=%9.2f fac=%-14s %s\n", $n, $parts[0], $parts[1], (float)$parts[2], $match['fac'] ?? '?', substr($match['razon'] ?? '', 0, 26));
}
echo "\n=== Cabecera tiene, ERP NO: $extraCount facturas, sumatoria = " . number_format($extraSum, 2) . " ===\n";
foreach ($extra as $k => $n) {
    $parts = explode('|', $k);
    printf("  x%-2d prov=%-6s %s tot=%9.2f\n", $n, $parts[0], $parts[1], (float)$parts[2]);
}
echo "\nRESUMEN: ERP=" . count($erpRows) . " Cabecera=" . count($dbList) . " | faltan en cabecera=$missCount (${missSum}) | extra en cabecera=$extraCount (${extraSum})\n";