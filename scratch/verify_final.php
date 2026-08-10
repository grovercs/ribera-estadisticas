<?php
require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$erp=DB::connection('erp');
$base="FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa AND i.cod_proveedor=c.cod_proveedor WHERE c.cod_empresa=1";
function m($erp,$sql,$b,$lbl,$exp){$r=$erp->select($sql,$b)[0];printf("  %-26s count=%5d sum=%12.2f  (esp %s)\n",$lbl,$r->c,$r->s,$exp);}
echo "=== 5 metricas con JOIN por (fac,emp,prov) ===\n";
m($erp,"SELECT COUNT(*) c, SUM(i.importe) s $base AND YEAR(c.fecha_factura)=2026 AND MONTH(c.fecha_factura)=MONTH(GETDATE())",[],"2026 mes actual","236/294066.72");
m($erp,"SELECT COUNT(*) c, SUM(i.importe) s $base AND YEAR(c.fecha_factura)=2026 AND MONTH(c.fecha_factura)=MONTH(DATEADD(MONTH,-1,GETDATE()))",[],"2026 mes anterior","487/657575.31");
m($erp,"SELECT COUNT(*) c, SUM(i.importe) s $base AND YEAR(c.fecha_factura)=2026",[],"2026 year actual","2715/2752955.49");
m($erp,"SELECT COUNT(*) c, SUM(i.importe) s $base AND c.fecha_factura>=? AND c.fecha_factura<=?",["20250101 00:00:00","20250628 23:59:59"],"2025 periodo","3092/2778886.19");
m($erp,"SELECT COUNT(*) c, SUM(i.importe) s $base AND YEAR(c.fecha_factura)=2025",[],"2025 year anterior","6572/6170109.55");
