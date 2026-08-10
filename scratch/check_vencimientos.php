<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== TODOS LOS REGISTROS EN devoluciones_vencimientos_ventas (SIN FILTROS) ===\n";
$res = $db->select("
    SELECT 
        cod_devolucion,
        cod_factura_destino,
        cod_empresa_destino,
        tipo_factura_destino,
        numero_destino,
        importe_cliente,
        importe_propio
    FROM devoluciones_vencimientos_ventas
    ORDER BY cod_devolucion DESC
");

echo "Total registros: " . count($res) . "\n";
// Mostrar los primeros 30 registros
foreach (array_slice($res, 0, 30) as $r) {
    echo "  Devolucion: {$r->cod_devolucion} | Factura Destino: {$r->cod_factura_destino} | Importe Cliente: {$r->importe_cliente} | Importe Propio: {$r->importe_propio}\n";
}
