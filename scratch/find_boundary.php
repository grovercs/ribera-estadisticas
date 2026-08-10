<?php
require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$erp=DB::connection('erp');
echo "=== impuestos lines con fecha_factura<=Jun28 pero fecha_contabilizacion>Jun28 (2025) ===\n";
foreach($erp->select("SELECT i.cod_proveedor prov, CONVERT(date,c.fecha_factura) ff, CONVERT(date,c.fecha_contabilizacion) fc, i.porcentaje, i.base, i.importe_porcentaje iva, i.importe tot, c.cod_factura
  FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa AND i.cod_proveedor=c.cod_proveedor
  WHERE c.cod_empresa=1 AND c.fecha_factura<=? AND c.fecha_factura>=? AND c.fecha_contabilizacion>?
  ORDER BY c.fecha_factura",["20250628 23:59:59","20250101 00:00:00","20250628 23:59:59"]) as $r)
  printf("  prov=%-6s ffac=%s fcont=%s porc=%s base=%9.2f iva=%8.2f tot=%9.2f fac=%s\n",$r->prov,$r->ff,$r->fc,$r->porcentaje,(float)$r->base,(float)$r->iva,(float)$r->tot,$r->cod_factura);

// Suma de esas lineas
$r=$erp->select("SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa AND i.cod_proveedor=c.cod_proveedor WHERE c.cod_empresa=1 AND c.fecha_factura<=? AND c.fecha_factura>=? AND c.fecha_contabilizacion>?",["20250628 23:59:59","20250101 00:00:00","20250628 23:59:59"])[0];
printf("\n  Total: %d lineas, suma %.2f\n",$r->c,$r->s);

// Probar: periodo por fecha_factura EXCLUYENDO las que se contabilizaron despues
echo "\n=== Periodo: fecha_factura en H1 AND (fecha_contab IS NULL OR fecha_contab<=Jun28) ===\n";
$r=$erp->select("SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa AND i.cod_proveedor=c.cod_proveedor WHERE c.cod_empresa=1 AND c.fecha_factura>=? AND c.fecha_factura<=? AND (c.fecha_contabilizacion IS NULL OR c.fecha_contabilizacion<=?)",["20250101 00:00:00","20250628 23:59:59","20250628 23:59:59"])[0];
printf("  count=%d sum=%.2f  (esp 3092/2778886.19)\n",$r->c,$r->s);
