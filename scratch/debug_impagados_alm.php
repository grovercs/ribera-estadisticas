<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

echo "=== Forma Z vencimientos: cod_almacen real (via JOIN) ===\n";
foreach ($erp->select("SELECT f.cod_almacen, COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')
    GROUP BY f.cod_almacen") as $r)
    printf("  cod_almacen=%s count=%d pend=%.2f\n", $r->cod_almacen ?? 'NULL', $r->c, $r->pend);

echo "\n=== Probando el WHERE f.cod_almacen=1 directamente ===\n";
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND f.cod_almacen = 1")[0];
printf("  f.cod_almacen=1: count=%d pend=%.2f\n", $r->c, $r->pend);
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND f.cod_almacen = 2")[0];
printf("  f.cod_almacen=2: count=%d pend=%.2f\n", $r->c, $r->pend);
$r = $erp->select("SELECT COUNT(*) c, SUM(v.importe-v.importe_cobrado) pend
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
    WHERE v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC') AND f.cod_almacen IN (1,2)")[0];
printf("  f.cod_almacen IN(1,2): count=%d pend=%.2f\n", $r->c, $r->pend);

// Re-run controller method explicitly per tienda
echo "\n=== Controller detalleImpagados(tipo=impagados) por tienda ===\n";
$ctrl = app(App\Http\Controllers\StoreDashboardController::class);
foreach (['1','2','all'] as $t) {
    $req = Illuminate\Http\Request::create('/x','GET',['tipo'=>'impagados','tienda'=>$t]);
    $j = json_decode($ctrl->detalleImpagados($req)->getContent(),true);
    $sum=array_sum(array_column($j['data']??[],'importe_pendiente'));
    printf("  tienda=%s count=%d sum=%.2f\n",$t,count($j['data']??[]),$sum);
}