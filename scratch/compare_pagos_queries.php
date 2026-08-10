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

function mostrar($titulo, $res, $esperado) {
    echo "--- $titulo ---\n";
    $tot = 0;
    foreach ($esperado as $k => $v) {
        $found = null;
        foreach ($res as $r) { if (($r->periodo ?? null) === $k) { $found = $r->importe; break; } }
        $diff = $found !== null ? $found - $v : null;
        printf("  %-15s esperado=%12.2f  obtenido=%s  diff=%s\n",
            $k, $v,
            $found !== null ? sprintf("%12.2f", $found) : "   (no hay)  ",
            $diff !== null ? sprintf("%+10.2f", $diff) : "    -    "
        );
        if ($found !== null) $tot += $found;
    }
    foreach ($res as $r) {
        if (!array_key_exists($r->periodo, $esperado)) {
            printf("  %-15s (extra)        obtenido=%12.2f\n", $r->periodo, $r->importe);
            $tot += $r->importe;
        }
    }
    printf("  TOTAL obtenido=%12.2f  esperado=%12.2f  diff=%+10.2f\n\n", $tot, array_sum($esperado), $tot - array_sum($esperado));
}

// A) Query ANTIGUA: pagos_vencimientos_facturas, p.importe, p.fecha >= GETDATE()
try {
    $res = $erp->select("
        SELECT
            CASE
                WHEN p.fecha <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                ELSE 'Mas de 3 meses'
            END as periodo,
            SUM(p.importe) as importe
        FROM pagos_vencimientos_facturas p
        WHERE p.importe > 0 AND p.fecha IS NOT NULL AND p.fecha >= GETDATE()
        GROUP BY
            CASE
                WHEN p.fecha <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                ELSE 'Mas de 3 meses'
            END
    ");
    mostrar('A) ANTIGUA pagos_vencimientos_facturas (importe, fecha>=hoy)', $res, $esperado);
} catch (\Exception $e) {
    echo "A) ERROR: ".$e->getMessage()."\n\n";
}

// B) Nueva tabla vencimientos_facturas_compras, SOLO futuros (sin Vencidos), importe total
try {
    $res = $erp->select("
        SELECT
            CASE
                WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                ELSE 'Mas de 3 meses'
            END as periodo,
            SUM(p.importe) as importe
        FROM vencimientos_facturas_compras p
        WHERE p.importe > 0 AND p.fecha_vencimiento IS NOT NULL AND p.fecha_vencimiento >= GETDATE()
        GROUP BY
            CASE
                WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                ELSE 'Mas de 3 meses'
            END
    ");
    mostrar('B) vencimientos_facturas_compras (importe total, solo futuros)', $res, $esperado);
} catch (\Exception $e) {
    echo "B) ERROR: ".$e->getMessage()."\n\n";
}

// C) Nueva tabla, futuros, pendiente = importe - importe_pagado
try {
    $res = $erp->select("
        SELECT
            CASE
                WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                ELSE 'Mas de 3 meses'
            END as periodo,
            SUM(p.importe - p.importe_pagado) as importe
        FROM vencimientos_facturas_compras p
        WHERE (p.importe - p.importe_pagado) > 0 AND p.fecha_vencimiento IS NOT NULL AND p.fecha_vencimiento >= GETDATE()
        GROUP BY
            CASE
                WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                ELSE 'Mas de 3 meses'
            END
    ");
    mostrar('C) vencimientos_facturas_compras (importe-importe_pagado, solo futuros)', $res, $esperado);
} catch (\Exception $e) {
    echo "C) ERROR: ".$e->getMessage()."\n\n";
}

echo "Esperado total: $esperadoTotal\n";