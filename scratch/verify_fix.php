<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');
$dia = '2026-06-27';

echo "=== VERIFICACION FINAL MÁRGENES $dia ===\n";
echo "Referencia ERP: Vielha Venta=7667.21 Coste=5149.76 %=32.83 | Pont Venta=1046.08 Coste=535.68 %=48.79\n\n";

$result = $db->select("
    SELECT
        v.cod_almacen,
        SUM(v.importe) as venta,
        ISNULL((SELECT SUM(l.precio_coste * l.cantidad)
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
                    AND l.tipo_venta = vc.tipo_venta
                    AND l.cod_empresa = vc.cod_empresa
                    AND l.cod_caja = vc.cod_caja
                WHERE CAST(vc.fecha_venta AS DATE) = ?
                    AND vc.tipo_venta IN (2, 4, 5)
                    AND vc.cod_almacen = v.cod_almacen
                    AND l.precio_coste IS NOT NULL
                    AND ISNULL(vc.anulada,'') <> 'S'), 0) as coste
    FROM hist_ventas_cabecera v
    WHERE CAST(v.fecha_venta AS DATE) = ?
        AND v.tipo_venta IN (2, 4, 5)
        AND ISNULL(v.anulada, '') <> 'S'
    GROUP BY v.cod_almacen
", [$dia, $dia]);

foreach ($result as $r) {
    $margen = $r->venta - $r->coste;
    $pct = $r->venta > 0 ? ($margen / $r->venta) * 100 : 0;
    $nombre = $r->cod_almacen == 1 ? 'Pont de Suert' : 'Vielha       ';
    echo "$nombre: Venta=" . number_format($r->venta, 2) .
         " | Coste=" . number_format($r->coste, 2) .
         " | Margen=" . number_format($pct, 2) . "%\n";
}
