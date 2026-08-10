<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== CHECKING COMPANIES AND ALMACENES ===\n";

$res = $db->select("
    SELECT cod_empresa, COUNT(*) as cnt
    FROM vencimientos_facturas
    GROUP BY cod_empresa
");
print_r($res);

$res2 = $db->select("
    SELECT f.cod_empresa, f.cod_almacen, COUNT(*) as cnt
    FROM facturas_ventas_cabecera f
    GROUP BY f.cod_empresa, f.cod_almacen
");
print_r($res2);
