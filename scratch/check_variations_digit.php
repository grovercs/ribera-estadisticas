<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== VARIATIONS OF PENDING FOR DIGIT 1 ===\n";

$queries = [
    "No dev excl, pend > 0" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        WHERE (v.importe - v.importe_cobrado) > 0
          AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
    ",
    "No dev excl, pend <> 0" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        WHERE (v.importe - v.importe_cobrado) <> 0
          AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
    ",
    "With dev excl, pend > 0" => "
        SELECT COUNT(*) as cnt, SUM(v.importe - v.importe_cobrado) as sum_pend
        FROM vencimientos_facturas v
        WHERE (v.importe - v.importe_cobrado) > 0
          AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
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
        WHERE (v.importe - v.importe_cobrado) <> 0
          AND SUBSTRING(CAST(v.cod_factura AS VARCHAR), 3, 1) = '1'
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

echo "\n=== WHAT ABOUT DIGIT 2? ===\n";
foreach ($queries as $name => $sql) {
    $sql2 = str_replace("= '1'", "= '2'", $sql);
    $res = $db->select($sql2);
    echo sprintf("  %35s | Count: %d | Sum: %s\n",
        $name, $res[0]->cnt, number_format($res[0]->sum_pend, 2)
    );
}
