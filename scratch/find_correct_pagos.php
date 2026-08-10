<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$erp = DB::connection('erp');

$esperado = [
    'Mes Actual'      => 343230.92,
    'Mes Siguiente'   => 306844.06,
    'En 2 meses'      => 102659.03,
    'En 3 meses'      => 21611.53,
];
$esperadoTotal = 774345.54;

function meses($res, $esperado) {
    $tot = 0; $ok = true;
    foreach ($esperado as $k => $v) {
        $found = null;
        foreach ($res as $r) { if (($r->periodo ?? null) === $k) { $found = $r->importe; break; } }
        $diff = $found !== null ? $found - $v : null;
        $mark = ($diff !== null && abs($diff) < 1) ? 'OK' : 'XX';
        if ($mark === 'XX') $ok = false;
        printf("    %-15s esp=%11.2f  obt=%s  diff=%s  %s\n",
            $k, $v,
            $found !== null ? sprintf("%11.2f", $found) : "  (nada)  ",
            $diff !== null ? sprintf("%+8.2f", $diff) : "   -   ",
            $mark
        );
        if ($found !== null) $tot += $found;
    }
    foreach ($res as $r) {
        if (!array_key_exists($r->periodo, $esperado)) {
            printf("    %-15s (extra)       obt=%11.2f\n", $r->periodo, $r->importe);
            $tot += $r->importe;
        }
    }
    printf("    TOTAL obt=%11.2f  esp=%11.2f  diff=%+8.2f  %s\n", $tot, $esperadoTotal, $tot-$esperadoTotal, abs($tot-$esperadoTotal)<1?'OK':'XX');
    return $ok;
}

$baseCase = "
    CASE
        WHEN v.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
        WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
        WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
        WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
        ELSE 'Mas de 3 meses'
    END
";

$fpFilters = [
    'all'            => "1=1",
    'excl_z_fps'     => "v.cod_forma_liquidacion NOT LIKE 'Z%'",
    'excl_zjuz_zimp' => "v.cod_forma_liquidacion NOT IN ('ZJUZ','ZIMP')",
    'only_z_fps'     => "v.cod_forma_liquidacion LIKE 'Z%'",
];

foreach ($fpFilters as $name => $fpf) {
    echo "\n=== vencimientos_facturas | futuros | pend=importe-importe_cobrado | FP=$name ===\n";
    try {
        $res = $erp->select("
            SELECT $baseCase as periodo, SUM(v.importe - v.importe_cobrado) as importe
            FROM vencimientos_facturas v
            WHERE (v.importe - v.importe_cobrado) > 0
              AND v.fecha_vencimiento IS NOT NULL
              AND v.fecha_vencimiento >= GETDATE()
              AND $fpf
            GROUP BY $baseCase
        ");
        meses($res, $esperado);
    } catch (\Exception $e) { echo "  ERROR: ".$e->getMessage()."\n"; }
}

// Probar también sin filtro de "pagado" (importe total futuro) y solo por emitido
echo "\n=== vencimientos_facturas | futuros | importe TOTAL (sin restar cobrado) | all FP ===\n";
try {
    $res = $erp->select("
        SELECT $baseCase as periodo, SUM(v.importe) as importe
        FROM vencimientos_facturas v
        WHERE v.importe > 0 AND v.fecha_vencimiento IS NOT NULL AND v.fecha_vencimiento >= GETDATE()
        GROUP BY $baseCase
    ");
    meses($res, $esperado);
} catch (\Exception $e) { echo "  ERROR: ".$e->getMessage()."\n"; }

echo "\nEsperado: ".implode(' | ', array_map(fn($k,$v)=>"$k=$v", array_keys($esperado), $esperado))." | TOTAL=$esperadoTotal\n";