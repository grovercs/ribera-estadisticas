<?php
require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$erp=DB::connection('erp');
function nf($s){ if(preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#',trim($s),$m)) return $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT); return trim($s); }
$raw=preg_replace('/^\xEF\xBB\xBF/','',file_get_contents(__DIR__.'/erp_compras_2025.csv'));
$lines=array_filter(preg_split('/\r\n|\n|\r/',$raw),fn($l)=>trim($l)!=='');
$csvKeys=[];
foreach($lines as $lin){$p=array_map(fn($x)=>trim($x),explode(';',$lin));if(count($p)<8||$p[2]===''||$p[3]==='')continue;$k=$p[3].'|'.nf($p[2]).'|'.round((float)str_replace(',','.',$p[5]),2).'|'.round((float)str_replace(',','.',$p[6]),2);$csvKeys[$k]=($csvKeys[$k]??0)+1;}
echo "CSV sample keys (841 26/02):\n";
foreach($csvKeys as $k=>$n) if(str_starts_with($k,'841|2025-02-26')) echo "  [$k] x$n\n";
$rows=$erp->select("SELECT i.cod_proveedor prov, CONVERT(date,c.fecha_factura) f, round(i.base,2) base, round(i.importe_porcentaje,2) iva FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2025 AND i.cod_proveedor=841 AND CONVERT(date,c.fecha_factura)='2025-02-26'");
echo "impuestos keys (841 26/02):\n";
foreach($rows as $r){ $k=$r->prov.'|'.$r->f.'|'.$r->base.'|'.$r->iva; echo "  [$k] isset_csv=".(isset($csvKeys[$k])?'YES':'NO')."\n"; }
echo "\nCSV total unique keys: ".count($csvKeys)."\n";
// imprimir 3 CSV keys cualesquiera
echo "CSV first 3 keys:\n"; $i=0; foreach($csvKeys as $k=>$n){echo "  [$k]\n";if(++$i>=3)break;}
echo "impuestos first 3 keys (all 2025):\n";
$all=$erp->select("SELECT TOP 3 i.cod_proveedor prov, CONVERT(date,c.fecha_factura) f, round(i.base,2) base, round(i.importe_porcentaje,2) iva FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2025");
foreach($all as $r) echo "  [".$r->prov.'|'.$r->f.'|'.$r->base.'|'.$r->iva."]\n";
