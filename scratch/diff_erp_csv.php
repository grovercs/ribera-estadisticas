<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

$csvPath = __DIR__ . '/erp_compras_2025.csv';
$raw = file_get_contents($csvPath);
// quitar BOM si lo hay
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$lines = preg_split('/\r\n|\n|\r/', $raw);
$lines = array_filter($lines, fn($l) => trim($l) !== '');

echo "=== Total lineas CSV: " . count($lines) . " ===\n\n";

// Parser robusto: anclar por fecha dd/mm/yyyy
$erpRows = []; // key => [cod_factura, fecha, cod_prov, base, iva, razon]
$bad = 0;
$fechas = [];
foreach ($lines as $lin) {
    $parts = explode(';', $lin);
    $parts = array_map(fn($p) => trim($p), $parts);
    // buscar campo fecha
    $fechaIdx = null;
    foreach ($parts as $i => $p) {
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $p)) { $fechaIdx = $i; break; }
    }
    if ($fechaIdx === null) { $bad++; continue; }
    $codFact = $parts[$fechaIdx - 1] ?? '';
    $fecha = $parts[$fechaIdx];
    $codProv = $parts[$fechaIdx + 1] ?? '';
    $razon = $parts[$fechaIdx + 2] ?? '';
    $base = isset($parts[$fechaIdx + 3]) ? str_replace(',', '.', $parts[$fechaIdx + 3]) : '0';
    $iva = isset($parts[$fechaIdx + 4]) ? str_replace(',', '.', $parts[$fechaIdx + 4]) : '0';
    // normalizar fecha a YYYY-MM-DD
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $fecha, $m)) {
        $f = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    } else { $f = $fecha; }
    $fechas[] = $f;
    $key = $codProv . '|' . $codFact;
    $erpRows[$key] = ['cod_factura' => $codFact, 'fecha' => $f, 'cod_prov' => $codProv, 'razon' => $razon, 'base' => (float)$base, 'iva' => (float)$iva];
}
echo "Filas parseadas OK: " . count($erpRows) . " | bad: $bad\n";
echo "Rango fechas: " . min($fechas) . " -> " . max($fechas) . "\n";
// total base e iva del CSV
$totBase = array_sum(array_column($erpRows, 'base'));
$totIva = array_sum(array_column($erpRows, 'iva'));
printf("CSV SUM base=%.2f  iva=%.2f  base+iva=%.2f  (ERP esperado total 6170109.55)\n", $totBase, $totIva, $totBase + $totIva);

// Cargar cabecera empresa=1, 2025
$dbRows = $erp->select("SELECT cod_factura, cod_proveedor, CONVERT(date, fecha_factura) as f, importe, importe_impuestos
    FROM facturas_compras_cabecera
    WHERE cod_empresa=1 AND YEAR(fecha_factura)=2025");
$dbMap = [];
foreach ($dbRows as $r) {
    $key = $r->cod_proveedor . '|' . $r->cod_factura;
    $dbMap[$key] = ['importe' => (float)$r->importe, 'imp_impuestos' => (float)$r->importe_impuestos, 'fecha' => $r->f];
}
echo "\nCabecera empresa=1 2025: " . count($dbRows) . " filas\n";

// En CSV pero NO en cabecera
$onlyCsv = [];
foreach ($erpRows as $k => $v) {
    if (!isset($dbMap[$k])) $onlyCsv[$k] = $v;
}
echo "\n=== EN CSV pero NO en cabecera (empresa=1): " . count($onlyCsv) . " ===\n";
$sumOnly = 0;
foreach ($onlyCsv as $v) {
    printf("  prov=%-6s fac=%-18s %s base=%9.2f iva=%8.2f  %s\n", $v['cod_prov'], $v['cod_factura'], $v['fecha'], $v['base'], $v['iva'], substr($v['razon'],0,30));
    $sumOnly += $v['base'] + $v['iva'];
}
printf("  SUM base+iva de los que faltan: %.2f\n", $sumOnly);

// En cabecera pero NO en CSV
$onlyDb = [];
foreach ($dbMap as $k => $v) {
    if (!isset($erpRows[$k])) $onlyDb[$k] = $v;
}
echo "\n=== EN cabecera pero NO en CSV: " . count($onlyDb) . " ===\n";
$cnt = 0;
foreach ($onlyDb as $k => $v) {
    if ($cnt++ >= 30) { echo "  ... (mas)\n"; break; }
    printf("  key=%-25s %s imp=%.2f impIV=%.2f\n", $k, $v['fecha'], $v['importe'], $v['imp_impuestos']);
}