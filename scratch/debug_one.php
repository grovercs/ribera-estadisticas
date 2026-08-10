<?php
require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$erp=DB::connection('erp');
echo "cabecera 841 2025-02-26:\n";
foreach($erp->select("SELECT cod_factura,cod_proveedor,CONVERT(date,fecha_factura) f,importe,importe_impuestos FROM facturas_compras_cabecera WHERE cod_proveedor=841 AND CONVERT(date,fecha_factura)='2025-02-26'") as $r)
  printf("  fac=%s prov=%s f=%s imp=%.2f impIV=%.2f\n",$r->cod_factura,$r->cod_proveedor,$r->f,(float)$r->importe,(float)$r->importe_impuestos);
echo "impuestos para esos cod_factura:\n";
foreach($erp->select("SELECT i.cod_factura,i.cod_proveedor,i.cod_impuesto,i.porcentaje,i.base,i.importe_porcentaje,i.importe FROM impuestos_facturas_compras i WHERE i.cod_proveedor=841 AND i.cod_factura IN (SELECT cod_factura FROM facturas_compras_cabecera WHERE cod_proveedor=841 AND CONVERT(date,fecha_factura)='2025-02-26')") as $r)
  printf("  fac=%s prov=%s codimp=%s porc=%s base=%.2f iva=%s tot=%s\n",$r->cod_factura,$r->cod_proveedor,$r->cod_impuesto,$r->porcentaje,(float)$r->base,$r->importe_porcentaje,$r->importe);
