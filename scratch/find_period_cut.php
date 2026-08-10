<?php
require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$erp=DB::connection('erp');
$base="FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa AND i.cod_proveedor=c.cod_proveedor WHERE c.cod_empresa=1 AND c.fecha_factura>=?";
$cuts=["20250101 00:00:00"];
$ends=["20250531 23:59:59"=>"May31","20250627 23:59:59"=>"Jun27","20250628 23:59:59"=>"Jun28","20250629 23:59:59"=>"Jun29","20250630 23:59:59"=>"Jun30","20250701 00:00:00"=>"Jul01start"];
echo "Esperado: 3092 / 2778886.19\n";
foreach($ends as $end=>$lbl){
  $r=$erp->select("SELECT COUNT(*) c, SUM(i.importe) s $base AND c.fecha_factura<=?",["20250101 00:00:00",$end])[0];
  printf("  %s : count=%5d sum=%12.2f\n",$lbl,$r->c,$r->s);
}
// probar tambien con fecha_contabilizacion para el corte
echo "\nCon YEAR(c.fecha_factura)=2025 y filtro por fecha_contabilizacion <= Jun28:\n";
$r=$erp->select("SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa AND i.cod_proveedor=c.cod_proveedor WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2025 AND c.fecha_contabilizacion<=?",["20250628 23:59:59"])[0];
printf("  count=%5d sum=%12.2f\n",$r->c,$r->s);
