<?php
require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$erp=DB::connection('erp');
function nf($s){ if(preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#',trim($s),$m)) return $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT); return trim($s); }
function fk($v){ return number_format((float)$v, 2, '.', ''); }

$raw=preg_replace('/^\xEF\xBB\xBF/','',file_get_contents(__DIR__.'/erp_compras_2025.csv'));
$lines=array_filter(preg_split('/\r\n|\n|\r/',$raw),fn($l)=>trim($l)!=='');
$csvKeys=[];
foreach($lines as $lin){
  $p=array_map(fn($x)=>trim($x),explode(';',$lin));
  if(count($p)<8||$p[2]===''||$p[3]==='')continue;
  $k=$p[3].'|'.nf($p[2]).'|'.fk(str_replace(',','.',$p[5])).'|'.fk(str_replace(',','.',$p[6]));
  $csvKeys[$k]=($csvKeys[$k]??0)+1;
}
echo "CSV multiset rows: ".array_sum($csvKeysKeys=$csvKeys)." | unique: ".count($csvKeys)."\n";

$rows=$erp->select("SELECT i.cod_proveedor prov, CONVERT(date,c.fecha_factura) f, i.cod_impuesto, i.porcentaje, i.base, i.importe_porcentaje, i.importe
  FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa
  WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2025");
echo "impuestos lines 2025 emp1: ".count($rows)."\n";
$in=0;$notIn=0;$notSum=0;$excl=['porc0'=>0,'base0'=>0,'recargo'=>0,'iva_null'=>0,'otro'=>0];$samp=[];
foreach($rows as $r){
  $k=$r->prov.'|'.$r->f.'|'.fk($r->base).'|'.fk($r->importe_porcentaje);
  if(isset($csvKeys[$k])&&$csvKeys[$k]>0){$csvKeys[$k]--;$in++;}
  else{$notIn++;$notSum+=(float)$r->importe;
    if((float)$r->porcentaje==0.0)$excl['porc0']++;
    elseif((float)$r->base==0.0)$excl['base0']++;
    elseif($r->importe_porcentaje===null)$excl['iva_null']++;
    else$excl['otro']++;
    if(count($samp)<20)$samp[]=$r;
  }
}
echo "\nimpuestos EN csv: $in | NO en csv (excluidas): $notIn (suma tot=$notSum)\n";
print_r($excl);
echo "\nMuestra excluidas:\n";
foreach($samp as $r) printf("  prov=%-6s %s codimp=%s porc=%s base=%9.2f iva=%9.2f tot=%9.2f\n",$r->prov,$r->f,$r->cod_impuesto,$r->porcentaje,(float)$r->base,(float)$r->importe_porcentaje,(float)$r->importe);
$leftover=0;foreach($csvKeys as $n)$leftover+=$n;
echo "\nCSV rows sin match en impuestos: $leftover\n";
