<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

$inUserList = ['522001113', '522004298'];

echo "=== IN USER LIST ===\n";
foreach ($inUserList as $cod) {
    $res = $db->select("SELECT * FROM vencimientos_facturas WHERE cod_factura = ?", [$cod]);
    print_r($res);
}

echo "=== NOT IN USER LIST (OTHER Z650) ===\n";
$res2 = $db->select("
    SELECT TOP 2 * 
    FROM vencimientos_facturas 
    WHERE cod_forma_liquidacion = 'Z650' 
      AND cod_factura NOT IN ('522001113', '522004298')
      AND (importe - importe_cobrado) <> 0
");
print_r($res2);
