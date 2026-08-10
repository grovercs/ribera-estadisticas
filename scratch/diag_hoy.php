<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== DIAGNÓSTICO MÁRGENES HOY ===\n\n";

// ¿Qué fecha tiene SQL Server?
$today = $db->select("SELECT CAST(GETDATE() AS DATE) as hoy, GETDATE() as ahora");
echo "SQL Server hoy: " . $today[0]->hoy . " | " . $today[0]->ahora . "\n";

// ¿Cuál es el último registro en hist_ventas_cabecera?
$maxFecha = $db->select("
    SELECT MAX(CAST(fecha_venta AS DATE)) as ultimo_dia
    FROM hist_ventas_cabecera
    WHERE tipo_venta IN (2,4,5)
");
echo "Último dia en hist_ventas: " . $maxFecha[0]->ultimo_dia . "\n";

// Ventas de hoy
$hoy = $db->select("
    SELECT cod_almacen, COUNT(*) as cnt, SUM(importe) as venta
    FROM hist_ventas_cabecera
    WHERE CAST(fecha_venta AS DATE) = CAST(GETDATE() AS DATE)
      AND tipo_venta IN (2,4,5)
      AND ISNULL(anulada,'') <> 'S'
    GROUP BY cod_almacen
");
echo "\nVentas de hoy (hist_ventas_cabecera):\n";
if (empty($hoy)) {
    echo "  -> SIN DATOS hoy\n";
} else {
    foreach ($hoy as $r) {
        echo "  Alm {$r->cod_almacen}: {$r->cnt} tickets | " . number_format($r->venta, 2) . " €\n";
    }
}

// Ventas de los últimos 3 días para ver qué hay reciente
$reciente = $db->select("
    SELECT TOP 5 CAST(fecha_venta AS DATE) as dia, cod_almacen, COUNT(*) as cnt
    FROM hist_ventas_cabecera
    WHERE tipo_venta IN (2,4,5) AND ISNULL(anulada,'') <> 'S'
    GROUP BY CAST(fecha_venta AS DATE), cod_almacen
    ORDER BY dia DESC
");
echo "\nÚltimos días con ventas:\n";
foreach ($reciente as $r) {
    echo "  {$r->dia} | Alm {$r->cod_almacen}: {$r->cnt} tickets\n";
}

// La tabla hist_ventas vs ventas_cabecera (en caliente)
// Quizás hoy está en ventas_cabecera (no hist_) porque aún no se ha archivado
$ventasHoy = $db->select("
    SELECT cod_almacen, COUNT(*) as cnt, SUM(importe) as venta
    FROM ventas_cabecera
    WHERE CAST(fecha_venta AS DATE) = CAST(GETDATE() AS DATE)
      AND tipo_venta IN (2,4,5)
      AND ISNULL(anulada,'') <> 'S'
    GROUP BY cod_almacen
");
echo "\nVentas de hoy (ventas_cabecera - tabla en caliente):\n";
if (empty($ventasHoy)) {
    echo "  -> SIN DATOS hoy\n";
} else {
    foreach ($ventasHoy as $r) {
        echo "  Alm {$r->cod_almacen}: {$r->cnt} tickets | " . number_format($r->venta, 2) . " €\n";
    }
}

// Último dia en ventas_cabecera
$maxVentas = $db->select("
    SELECT MAX(CAST(fecha_venta AS DATE)) as ultimo_dia
    FROM ventas_cabecera
    WHERE tipo_venta IN (2,4,5)
");
echo "Último dia en ventas_cabecera: " . $maxVentas[0]->ultimo_dia . "\n";
