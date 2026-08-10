<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Cargar CSV ERP como multiset por (prov, fecha, base, iva)
$raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(__DIR__ . '/erp_compras_2025.csv'));
$lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');
function nf($s){ if(preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#',trim($s),$m)) return $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT); return trim($s); }
$csvKeys = [];
foreach ($lines as $lin) {
    $p = array_map(fn($x)=>trim($x), explode(';', $lin));
    if (count($p) < 8 || $p[2]==='' || $p[3]==='') continue;
    $prov = $p[3]; $fecha = nf($p[2]);
    $base = round((float)str_replace(',', '.', $p[5]), 2);
    $iva = round((float)str_replace(',', '.', $p[6]), 2);
    $k = $prov.'|'.$fecha.'|'.$base.'|'.$iva;
    $csvKeys[$k] = ($csvKeys[$k] ?? 0) + 1;
}
echo "CSV multiset rows: " . array_sum($csvKeys) . "\n";

// impuestos JOIN cabecera (empresa=1, 2025) -> una fila por linea IVA
$rows = $erp->select("SELECT i.cod_proveedor prov, CONVERT(date,c.fecha_factura) f,
    i.cod_impuesto, i.porcentaje, i.recargo,
    round(i.base,2) base, round(i.importe_porcentaje,2) iva, round(i.importe,2) tot
    FROM impuestos_facturas_compras i
    JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa
    WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2025");
echo "impuestos lines 2025 empresa=1: " . count($rows) . "\n";

$inCsv = 0; $notInCsv = 0; $notSum = 0;
$exclBy = ['porc0' => 0, 'base0' => 0, 'recargo' => 0, 'iva_null' => 0, 'otro' => 0];
$notInRows = [];
foreach ($rows as $r) {
    $k = $r->prov.'|'.$r->f.'|'.$r->base.'|'.$r->iva;
    if (isset($csvKeys[$k]) && $csvKeys[$k] > 0) {
        $csvKeys[$k]--; $inCsv++;
    } else {
        $notInCsv++; $notSum += $r->tot;
        // clasificar
        if ((float)$r->porcentaje == 0.0) $exclBy['porc0']++;
        elseif ((float)$r->base == 0.0) $exclBy['base0']++;
        elseif ((float)$r->recargo != 0.0) $exclBy['recargo']++;
        elseif ($r->iva === null) $exclBy['iva_null']++;
        else $exclBy['otro']++;
        if (count($notInRows) < 25) $notInRows[] = $r;
    }
}
echo "\nimpuestos lines EN CSV: $inCsv\n";
echo "impuestos lines NO en CSV (excluidas por ERP): $notInCsv  (suma tot=$notSum)\n";
print_r($exclBy);

echo "\n=== Muestra de lineas impuestos NO en CSV (excluidas) ===\n";
foreach ($notInRows as $r) printf("  prov=%-6s %s codimp=%s porc=%s base=%9.2f iva=%8.2f tot=%9.2f\n", $r->prov, $r->f, $r->cod_impuesto, $r->porcentaje, (float)$r->base, (float)$r->iva, (float)$r->tot);

// Sobran en CSV (no matcheadas)
$csvLeftover = array_sum($csvKeys);
echo "\nCSV rows sin match en impuestos: $csvLeftover\n";