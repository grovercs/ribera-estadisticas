<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== VARIATIONS OF PENDING FOR STORE 1 ===\n";

$queries = [
    "No dev excl, pend > 0" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE (v.importe - v.importe_cobrado) > 0
          AND f.cod_almacen = 1
    ",
    "No dev excl, pend <> 0" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE (v.importe - v.importe_cobrado) <> 0
          AND f.cod_almacen = 1
    ",
    "With dev excl, pend > 0 (current)" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE (v.importe - v.importe_cobrado) > 0
          AND f.cod_almacen = 1
          AND NOT EXISTS (
              SELECT 1 FROM devoluciones_vencimientos_ventas d
              WHERE d.cod_factura_destino = v.cod_factura
                AND d.tipo_factura_destino = v.tipo_factura
                AND d.cod_empresa_destino = v.cod_empresa
                AND d.numero_destino = v.numero
          )
    ",
    "With dev excl, pend <> 0" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
            AND v.tipo_factura = f.tipo_factura 
            AND v.cod_empresa = f.cod_empresa
        WHERE (v.importe - v.importe_cobrado) <> 0
          AND f.cod_almacen = 1
          AND NOT EXISTS (
              SELECT 1 FROM devoluciones_vencimientos_ventas d
              WHERE d.cod_factura_destino = v.cod_factura
                AND d.tipo_factura_destino = v.tipo_factura
                AND d.cod_empresa_destino = v.cod_empresa
                AND d.numero_destino = v.numero
          )
    ",
];

foreach ($queries as $name => $sql) {
    $res = $db->select($sql);
    echo sprintf("  %35s | Count: %d | Sum: %s\n",
        $name, $res[0]->cnt, number_format($res[0]->sum_pend, 2)
    );
}

echo "\n=== WHAT ABOUT STORE 2? ===\n";
foreach ($queries as $name => $sql) {
    $sql2 = str_replace("f.cod_almacen = 1", "f.cod_almacen = 2", $sql);
    $res = $db->select($sql2);
    echo sprintf("  %35s | Count: %d | Sum: %s\n",
        $name, $res[0]->cnt, number_format($res[0]->sum_pend, 2)
    );
}
