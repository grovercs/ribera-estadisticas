<?php
// Parsea los CSV del ERP (pend_all / pend_venc / pend_novenc) y resume.
// Formato: ;cod_factura;fecha_factura;fecha_vencimiento;razon_social;importe;importe_pendiente
// Lineas TOTAL: ;;;cod_cliente;TOTAL :;importe;importe_pendiente  (se ignoran)

function parsePend($file) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file));
    $lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');
    $rows = []; $sum = 0;
    foreach ($lines as $lin) {
        $p = array_map(fn($x) => trim($x), explode(';', $lin));
        if (count($p) < 7) continue;
        if ($p[1] === '' || $p[4] === 'TOTAL :') continue; // sin cod_factura o es linea TOTAL
        $codFactura = $p[1];
        $fechaFac = $p[2];
        $fechaVenc = $p[3];
        $razon = $p[4];
        $importe = (float)str_replace(',', '.', $p[5]);
        $pend = (float)str_replace(',', '.', $p[6]);
        $rows[] = ['fac' => $codFactura, 'fechaFac' => $fechaFac, 'fechaVenc' => $fechaVenc, 'razon' => $razon, 'importe' => $importe, 'pend' => $pend];
        $sum += $pend;
    }
    return ['rows' => $rows, 'sum' => $sum];
}

foreach (['pend_all', 'pend_venc', 'pend_novenc'] as $name) {
    $r = parsePend(__DIR__ . "/{$name}.csv");
    printf("%-12s filas=%4d  sum_pend=%12.2f\n", $name, count($r['rows']), $r['sum']);
}

// Comprobar coherencia venc + novenc = all
$all = parsePend(__DIR__ . '/pend_all.csv')['rows'];
$venc = parsePend(__DIR__ . '/pend_venc.csv')['rows'];
$novenc = parsePend(__DIR__ . '/pend_novenc.csv')['rows'];
printf("\nvenc(%d) + novenc(%d) = %d   vs all(%d)\n", count($venc), count($novenc), count($venc)+count($novenc), count($all));

// Claves unicas por (fac, fechaVenc)
function keyset($rows) { $s = []; foreach ($rows as $r) { $k = $r['fac'].'|'.$r['fechaVenc']; $s[$k] = ($s[$k]??0)+1; } return $s; }
$ka = keyset($all); $kv = keyset($venc); $kn = keyset($novenc);
echo "\nClaves unicas all=".count($ka)." venc=".count($kv)." novenc=".count($kn)."\n";

// Guardar para siguiente paso
file_put_contents(__DIR__.'/pend_all_parsed.json', json_encode($all));
file_put_contents(__DIR__.'/pend_venc_parsed.json', json_encode($venc));
file_put_contents(__DIR__.'/pend_novenc_parsed.json', json_encode($novenc));
echo "\nMuestra all (3):\n";
foreach (array_slice($all, 0, 3) as $r) printf("  fac=%s fechaFac=%s venc=%s razon=%s imp=%.2f pend=%.2f\n", $r['fac'], $r['fechaFac'], $r['fechaVenc'], substr($r['razon'],0,30), $r['importe'], $r['pend']);
echo "\nUltimas all (2):\n";
foreach (array_slice($all, -2) as $r) printf("  fac=%s fechaFac=%s venc=%s razon=%s imp=%.2f pend=%.2f\n", $r['fac'], $r['fechaFac'], $r['fechaVenc'], substr($r['razon'],0,30), $r['importe'], $r['pend']);