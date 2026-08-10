<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING SECCIONES ===\n";

$res = $db->select("
    SELECT 
        cod_seccion,
        nombre_seccion,
        cod_almacen,
        COUNT(*) as cnt
    FROM facturas_ventas_cabecera
    GROUP BY cod_seccion, nombre_seccion, cod_almacen
    ORDER BY cod_seccion, nombre_seccion, cod_almacen
");

foreach ($res as $r) {
    echo "Seccion: {$r->cod_seccion} - {$r->nombre_seccion} | Almacen: {$r->cod_almacen} | Count: {$r->cnt}\n";
}
