<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');
$dia = '2026-06-27';

echo "=== COMPARATIVA MÁRGENES $dia ===\n";
echo "Referencia ERP: Vielha Venta=7667.21 Coste=5149.76 | Pont Venta=1046.08 Coste=535.68\n\n";

// 1. Query actual del controller
$actual = $db->select("
    SELECT
        v.cod_almacen,
        SUM(v.importe) as venta,
        ISNULL((SELECT SUM(l.precio_coste * l.cantidad)
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
                    AND l.tipo_venta = vc.tipo_venta AND l.cod_empresa = vc.cod_empresa AND l.cod_caja = vc.cod_caja
                WHERE CAST(vc.fecha_venta AS DATE) = ?
                    AND vc.tipo_venta IN (2, 4, 5)
                    AND vc.cod_almacen = v.cod_almacen
                    AND l.cod_articulo IS NOT NULL
                    AND l.cod_articulo NOT IN ('ALMACEN','FERRETERIA','SANITARIOS','COCINAS','MARMOLES')
                    AND l.precio_coste IS NOT NULL AND l.precio_coste > 0 AND l.precio_coste < 100000
                    AND l.cantidad > 0
                    AND ISNULL(vc.anulada,'') <> 'S'), 0) as coste
    FROM hist_ventas_cabecera v
    WHERE CAST(v.fecha_venta AS DATE) = ?
        AND v.tipo_venta IN (2, 4, 5)
        AND ISNULL(v.anulada, '') <> 'S'
    GROUP BY v.cod_almacen
", [$dia, $dia]);
echo "1. Query actual (importe sin IVA):\n";
foreach ($actual as $r) {
    $m = $r->venta - $r->coste;
    echo "   Alm {$r->cod_almacen}: Venta=" . number_format($r->venta,2) . " Coste=" . number_format($r->coste,2) . " Margen=" . number_format($r->venta>0?$m/$r->venta*100:0,2) . "%\n";
}

// 2. ¿Quizás el ERP usa importe_impuestos (con IVA)?
$conIva = $db->select("
    SELECT cod_almacen, SUM(importe_impuestos) as venta_iva, SUM(importe) as venta_sin_iva
    FROM hist_ventas_cabecera
    WHERE CAST(fecha_venta AS DATE) = ? AND tipo_venta IN (2,4,5) AND ISNULL(anulada,'') <> 'S'
    GROUP BY cod_almacen
", [$dia]);
echo "\n2. Venta con y sin IVA:\n";
foreach ($conIva as $r) {
    echo "   Alm {$r->cod_almacen}: con_iva=" . number_format($r->venta_iva,2) . " | sin_iva=" . number_format($r->venta_sin_iva,2) . "\n";
}

// 3. Coste SIN filtros de exclusión de artículos
$costeSinFiltro = $db->select("
    SELECT vc.cod_almacen, SUM(l.precio_coste * l.cantidad) as coste
    FROM hist_ventas_linea l
    INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
        AND l.tipo_venta = vc.tipo_venta AND l.cod_empresa = vc.cod_empresa AND l.cod_caja = vc.cod_caja
    WHERE CAST(vc.fecha_venta AS DATE) = ?
        AND vc.tipo_venta IN (2, 4, 5) AND ISNULL(vc.anulada,'') <> 'S'
        AND l.precio_coste > 0 AND l.cantidad > 0
    GROUP BY vc.cod_almacen
", [$dia]);
echo "\n3. Coste SIN filtro de artículos excluidos:\n";
foreach ($costeSinFiltro as $r) {
    echo "   Alm {$r->cod_almacen}: Coste=" . number_format($r->coste,2) . "\n";
}

// 4. Coste con TODOS los artículos (precio_coste puede ser 0 o nulo)
$costeTodo = $db->select("
    SELECT vc.cod_almacen,
           SUM(CASE WHEN l.precio_coste > 0 AND l.cantidad > 0 THEN l.precio_coste * l.cantidad ELSE 0 END) as coste_positivo,
           SUM(l.precio_coste * l.cantidad) as coste_total
    FROM hist_ventas_linea l
    INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
        AND l.tipo_venta = vc.tipo_venta AND l.cod_empresa = vc.cod_empresa AND l.cod_caja = vc.cod_caja
    WHERE CAST(vc.fecha_venta AS DATE) = ?
        AND vc.tipo_venta IN (2, 4, 5) AND ISNULL(vc.anulada,'') <> 'S'
    GROUP BY vc.cod_almacen
", [$dia]);
echo "\n4. Coste total (incluyendo negatvos/nulos):\n";
foreach ($costeTodo as $r) {
    echo "   Alm {$r->cod_almacen}: coste_positivo=" . number_format($r->coste_positivo,2) . " | coste_total=" . number_format($r->coste_total,2) . "\n";
}

// 5. ¿El ERP usa precio_coste_base en lugar de precio_coste?
$cols = $db->select("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'hist_ventas_linea' AND COLUMN_NAME LIKE '%coste%'
");
echo "\n5. Columnas de coste en hist_ventas_linea:\n";
foreach ($cols as $c) echo "   " . $c->COLUMN_NAME . "\n";

// 6. Muestra algunas líneas del día para Vielha
echo "\n6. Muestra de líneas (Alm 2, dia=$dia, top 10 por coste):\n";
$lineas = $db->select("
    SELECT TOP 10 l.cod_articulo, l.cantidad, l.precio_venta, l.precio_coste, l.precio_coste_base,
           (l.precio_coste * l.cantidad) as coste_total
    FROM hist_ventas_linea l
    INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
        AND l.tipo_venta = vc.tipo_venta AND l.cod_empresa = vc.cod_empresa AND l.cod_caja = vc.cod_caja
    WHERE CAST(vc.fecha_venta AS DATE) = ? AND vc.cod_almacen = 2
        AND vc.tipo_venta IN (2,4,5) AND ISNULL(vc.anulada,'') <> 'S'
        AND l.precio_coste > 0
    ORDER BY (l.precio_coste * l.cantidad) DESC
", [$dia]);
foreach ($lineas as $r) {
    echo "   Art:{$r->cod_articulo} | Cant:{$r->cantidad} | PV:{$r->precio_venta} | PC:{$r->precio_coste} | PCBase:{$r->precio_coste_base} | Total:{$r->coste_total}\n";
}
