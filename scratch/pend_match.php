<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

function parsePend($file) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file));
    $lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');
    $rows = []; $sumImp = 0; $sumPend = 0;
    foreach ($lines as $lin) {
        $p = array_map(fn($x) => trim($x), explode(';', $lin));
        if (count($p) < 7) continue;
        if ($p[1] === '' || $p[4] === 'TOTAL :') continue;
        $imp = (float)str_replace(',', '.', $p[5]);
        $pend = (float)str_replace(',', '.', $p[6]);
        $rows[] = ['fac' => $p[1], 'fechaFac' => $p[2], 'fechaVenc' => $p[3], 'razon' => $p[4], 'imp' => $imp, 'pend' => $pend];
        $sumImp += $imp; $sumPend += $pend;
    }
    return [$rows, $sumImp, $sumPend];
}

[$all, $sumImp, $sumPend] = parsePend(__DIR__ . '/pend_all.csv');
printf("pend_all: filas=%d  SUM imp(col5)=%12.2f  SUM pend(col6)=%12.2f\n", count($all), $sumImp, $sumPend);
printf("  (usuario dijo 706 / 343.233,17)\n\n");

// Normalizar fecha d/m/Y -> Y-m-d
function nf($s){ if(preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#',trim($s),$m)) return $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT); return trim($s); }

// Claves CSV por (fac, fechaVenc)
$csvKeys = [];
foreach ($all as $r) {
    $k = $r['fac'] . '|' . nf($r['fechaVenc']);
    $csvKeys[$k] = ($csvKeys[$k] ?? 0) + 1;
}
echo "CSV claves unicas (fac|venc): " . count($csvKeys) . "\n";

// Traer TODOS los vencimientos pendientes (importe-importe_cobrado)>0 con datos para clasificar
$rows = $erp->select("SELECT v.cod_factura, v.cod_empresa, v.tipo_factura, v.numero,
    CONVERT(date,v.fecha_vencimiento) as fvenc, CONVERT(date,v.fecha_factura) as ffac,
    v.cod_cliente, v.razon_social, v.cod_forma_liquidacion as forma, v.cod_remesa as remesa,
    v.importe, v.importe_cobrado,
    (v.importe - v.importe_cobrado) as pend,
    f.cod_almacen as alm,
    CASE WHEN EXISTS (SELECT 1 FROM devoluciones_vencimientos_ventas d
        WHERE d.cod_factura_destino=v.cod_factura AND d.tipo_factura_destino=v.tipo_factura
        AND d.cod_empresa_destino=v.cod_empresa AND d.numero_destino=v.numero) THEN 1 ELSE 0 END as tiene_dev
  FROM vencimientos_facturas v
  LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
  WHERE (v.importe - v.importe_cobrado) > 0");
echo "DB vencimientos pendientes (importe-importe_cobrado>0): " . count($rows) . "\n\n";

// Casar: para cada clave CSV, ver si existe en DB (por fac|fvenc). Construir set de DB que matchea.
$dbByKey = [];
foreach ($rows as $r) {
    $k = $r->cod_factura . '|' . $r->fvenc;
    $dbByKey[$k][] = $r;
}

$matched = 0; $matchedRows = [];
$csvNotInDb = 0;
foreach ($csvKeys as $k => $n) {
    if (isset($dbByKey[$k])) {
        // tomar uno (puede haber varios por numero/empresa)
        foreach ($dbByKey[$k] as $r) { $matched++; $matchedRows[] = $r; }
    } else {
        $csvNotInDb++;
    }
}
echo "CSV filas matcheadas en DB: $matched   (claves CSV sin match: $csvNotInDb)\n";
$sumMatchedPend = array_sum(array_map(fn($r) => (float)$r->pend, $matchedRows));
printf("Suma pend de filas DB matcheadas: %.2f\n", $sumMatchedPend);

// Analizar que tienen en comun los matched (forma, remesa, devolucion, alm)
echo "\n=== Perfil de filas DB matcheadas (706 del ERP) ===\n";
$porForma = []; $porRemesa = ['NULL'=>0,'TIENE'=>0]; $porDev=[0=>0,1=>0]; $porAlm=[];
$sumaPend = 0;
foreach ($matchedRows as $r) {
    $f = $r->forma ?? 'NULL';
    $porForma[$f] = ($porForma[$f]??0)+1;
    $porRemesa[$r->remesa===null?'NULL':'TIENE']++;
    $porDev[(int)$r->tiene_dev]++;
    $a = $r->alm ?? 'NULL';
    $porAlm[$a] = ($porAlm[$a]??0)+1;
    $sumaPend += (float)$r->pend;
}
printf("Suma pend (matched) = %.2f  count=%d\n", $sumaPend, count($matchedRows));
echo "Por almacen: "; print_r($porAlm);
echo "Por remesa: "; print_r($porRemesa);
echo "Por tiene_devolucion: "; print_r($porDev);
echo "Por forma (top): "; arsort($porForma); $i=0; foreach ($porForma as $f=>$c) { if($i++<15) echo "$f=$c  "; } echo "\n";

// Cuantas formas Z hay entre matched? (deberian ser 0, son pendientes no impagados)
$zMatched = 0;
foreach ($matchedRows as $r) if (in_array($r->forma, ['ZIMP','ZJUZ','ZPER','ZCYC'])) $zMatched++;
echo "Formas Z (impagados) entre matched: $zMatched\n";