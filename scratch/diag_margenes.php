<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');
$year = 2026;

echo "=== DIAGNÓSTICO MÁRGENES COMERCIALES ===\n\n";

// 1. Venta total del año sin filtrar
$venta = $db->select("
    SELECT cod_almacen, SUM(importe) as venta, COUNT(*) as tickets
    FROM hist_ventas_cabecera
    WHERE YEAR(fecha_venta) = ? AND tipo_venta IN (2,4,5) AND ISNULL(anulada,'') <> 'S'
    GROUP BY cod_almacen
", [$year]);
echo "Venta año (sin IVA, sin filtrar):\n";
foreach ($venta as $r) echo "  Alm {$r->cod_almacen}: {$r->tickets} tickets | " . number_format($r->venta, 2) . " €\n";

// 2. Coste total del año (lineas con precio_coste > 0)
$coste = $db->select("
    SELECT vc.cod_almacen,
           COUNT(DISTINCT vc.cod_venta) as ventas,
           SUM(l.precio_coste * l.cantidad) as coste
    FROM hist_ventas_linea l
    INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta AND l.tipo_venta = vc.tipo_venta AND l.cod_empresa = vc.cod_empresa AND l.cod_caja = vc.cod_caja
    WHERE YEAR(vc.fecha_venta) = ? AND vc.tipo_venta IN (2,4,5) AND ISNULL(vc.anulada,'') <> 'S'
      AND l.cod_articulo IS NOT NULL
      AND l.cod_articulo NOT IN ('ALMACEN','FERRETERIA','SANITARIOS','COCINAS','MARMOLES')
      AND l.precio_coste IS NOT NULL AND l.precio_coste > 0 AND l.precio_coste < 100000
      AND l.cantidad > 0
    GROUP BY vc.cod_almacen
", [$year]);
echo "\nCoste año (líneas con coste > 0):\n";
foreach ($coste as $r) echo "  Alm {$r->cod_almacen}: {$r->ventas} ventas | " . number_format($r->coste, 2) . " €\n";

// 3. Query actual del controller (corroborar los valores)
$margenes = $db->select("
    SELECT
        v.cod_almacen,
        SUM(v.importe) as venta,
        ISNULL((SELECT SUM(l.precio_coste * l.cantidad)
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
                    AND l.tipo_venta = vc.tipo_venta
                    AND l.cod_empresa = vc.cod_empresa
                    AND l.cod_caja = vc.cod_caja
                WHERE YEAR(vc.fecha_venta) = ?
                    AND vc.tipo_venta IN (2, 4, 5)
                    AND vc.cod_almacen = v.cod_almacen
                    AND l.cod_articulo IS NOT NULL
                    AND l.cod_articulo NOT IN ('ALMACEN','FERRETERIA','SANITARIOS','COCINAS','MARMOLES')
                    AND l.precio_coste IS NOT NULL AND l.precio_coste > 0 AND l.precio_coste < 100000
                    AND l.cantidad > 0
                    AND ISNULL(vc.anulada,'') <> 'S'), 0) as coste
    FROM hist_ventas_cabecera v
    WHERE YEAR(v.fecha_venta) = ?
        AND v.tipo_venta IN (2, 4, 5)
        AND ISNULL(v.anulada, '') <> 'S'
    GROUP BY v.cod_almacen
", [$year, $year]);

echo "\nMárgenes (query actual controller):\n";
foreach ($margenes as $r) {
    $margen = $r->venta - $r->coste;
    $pct = $r->venta > 0 ? ($margen / $r->venta) * 100 : 0;
    echo "  Alm {$r->cod_almacen}: Venta=" . number_format($r->venta, 2) . " | Coste=" . number_format($r->coste, 2) . " | Margen=" . number_format($margen, 2) . " | %=" . number_format($pct, 2) . "%\n";
}

// 4. ¿El usuario tiene valores de referencia del ERP para el margen?
// Verifiquemos si hay artículos sin precio de coste que distorsionan la venta
$sinCoste = $db->select("
    SELECT vc.cod_almacen,
           COUNT(DISTINCT vc.cod_venta) as ventas_sin_coste
    FROM hist_ventas_cabecera vc
    WHERE YEAR(vc.fecha_venta) = ? AND vc.tipo_venta IN (2,4,5) AND ISNULL(vc.anulada,'') <> 'S'
      AND NOT EXISTS (
          SELECT 1 FROM hist_ventas_linea l
          WHERE l.cod_venta = vc.cod_venta AND l.tipo_venta = vc.tipo_venta AND l.cod_empresa = vc.cod_empresa AND l.cod_caja = vc.cod_caja
            AND l.precio_coste > 0 AND l.cantidad > 0
      )
    GROUP BY vc.cod_almacen
", [$year]);
echo "\nVentas SIN ninguna linea con coste:\n";
foreach ($sinCoste as $r) echo "  Alm {$r->cod_almacen}: {$r->ventas_sin_coste} ventas\n";

// 5. Importe de ventas con coste vs sin coste
$conVsSin = $db->select("
    SELECT vc.cod_almacen,
           SUM(CASE WHEN has_cost.tiene = 1 THEN vc.importe ELSE 0 END) as venta_con_coste,
           SUM(CASE WHEN has_cost.tiene = 0 OR has_cost.tiene IS NULL THEN vc.importe ELSE 0 END) as venta_sin_coste
    FROM hist_ventas_cabecera vc
    LEFT JOIN (
        SELECT l.cod_venta, l.tipo_venta, l.cod_empresa, l.cod_caja, 1 as tiene
        FROM hist_ventas_linea l
        WHERE l.precio_coste > 0 AND l.cantidad > 0
        GROUP BY l.cod_venta, l.tipo_venta, l.cod_empresa, l.cod_caja
    ) has_cost ON vc.cod_venta = has_cost.cod_venta AND vc.tipo_venta = has_cost.tipo_venta AND vc.cod_empresa = has_cost.cod_empresa AND vc.cod_caja = has_cost.cod_caja
    WHERE YEAR(vc.fecha_venta) = ? AND vc.tipo_venta IN (2,4,5) AND ISNULL(vc.anulada,'') <> 'S'
    GROUP BY vc.cod_almacen
", [$year]);
echo "\nVenta con coste vs sin coste disponible:\n";
foreach ($conVsSin as $r) {
    echo "  Alm {$r->cod_almacen}: con_coste=" . number_format($r->venta_con_coste, 2) . " | sin_coste=" . number_format($r->venta_sin_coste, 2) . "\n";
}
