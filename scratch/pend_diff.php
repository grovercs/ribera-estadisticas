<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

function nf($s){ if(preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#',trim($s),$m)) return $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT); return trim($s); }

// CSV 706 claves (fac|venc)
$raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(__DIR__ . '/pend_all.csv'));
$lines = array_filter(preg_split('/\r\n|\n|\r/', $raw), fn($l) => trim($l) !== '');
$csvKeys = []; $csvRows = [];
foreach ($lines as $lin) {
    $p = array_map(fn($x)=>trim($x), explode(';', $lin));
    if (count($p)<7 || $p[1]==='' || $p[4]==='TOTAL :') continue;
    $k = $p[1] . '|' . nf($p[3]);
    $csvKeys[$k] = ($csvKeys[$k]??0)+1;
    $csvRows[$k] = ['fac'=>$p[1],'venc'=>nf($p[3]),'razon'=>$p[4],'pend'=>(float)str_replace(',','.',$p[6])];
}
echo "CSV claves: " . count($csvKeys) . "  (filas 706)\n";

$Z4 = "v.cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC')";

// Mi filtro candidato: remesa NULL + NOT Z4 + alm 1+2 (sin filtro pend)
$rows = $erp->select("SELECT v.cod_factura, CONVERT(date,v.fecha_vencimiento) as fvenc,
    v.cod_empresa, v.numero, v.cod_forma_liquidacion as forma, v.razon_social,
    v.importe, v.importe_cobrado, (v.importe-v.importe_cobrado) as pend, f.cod_almacen as alm
  FROM vencimientos_facturas v
  LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
  WHERE v.cod_remesa IS NULL AND NOT ($Z4)
    AND EXISTS (SELECT 1 FROM facturas_ventas_cabecera f2 WHERE f2.cod_factura=v.cod_factura AND f2.tipo_factura=v.tipo_factura AND f2.cod_empresa=v.cod_empresa AND f2.cod_almacen IN (1,2))");
echo "Filtro remesaNULL+NOT Z4+alm1+2: " . count($rows) . " filas, sum pend=" . array_sum(array_map(fn($r)=>(float)$r->pend,$rows)) . "\n";

$dbKeys = [];
foreach ($rows as $r) {
    $k = $r->cod_factura . '|' . $r->fvenc;
    $dbKeys[$k] = ($dbKeys[$k]??0)+1;
}

// CSV - DB (claves en CSV no en mi filtro)
$onlyCsv = [];
foreach ($csvKeys as $k=>$n) if (!isset($dbKeys[$k])) $onlyCsv[$k] = $csvRows[$k];
echo "\n=== En CSV (ERP) pero NO en mi filtro: " . count($onlyCsv) . " ===\n";
foreach ($onlyCsv as $k=>$r) printf("  fac=%s venc=%s pend=%.2f razon=%s\n", $r['fac'], $r['venc'], $r['pend'], substr($r['razon'],0,30));

// DB - CSV (claves en mi filtro no en CSV)
$onlyDb = [];
foreach ($dbKeys as $k=>$n) if (!isset($csvKeys[$k])) $onlyDb[$k] = $n;
echo "\n=== En mi filtro pero NO en CSV (ERP): " . count($onlyDb) . " ===\n";
$shown=0;
foreach ($rows as $r) { $k=$r->cod_factura.'|'.$r->fvenc; if (isset($onlyDb[$k]) && $shown<15) { printf("  fac=%s venc=%s emp=%s num=%s forma=%s pend=%.2f alm=%s razon=%s\n",$r->cod_factura,$r->fvenc,$r->cod_empresa,$r->numero,$r->forma,(float)$r->pend,$r->alm??'NULL',substr(trim($r->razon_social),0,24)); $shown++; } }

// Probar sin filtro alm (incluir NULL almacen)
$rows2 = $erp->select("SELECT v.cod_factura, CONVERT(date,v.fecha_vencimiento) as fvenc, (v.importe-v.importe_cobrado) pend, f.cod_almacen alm
  FROM vencimientos_facturas v
  LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura=f.cod_factura AND v.tipo_factura=f.tipo_factura AND v.cod_empresa=f.cod_empresa
  WHERE v.cod_remesa IS NULL AND NOT ($Z4)");
echo "\nSin filtro alm: " . count($rows2) . " filas, sum=" . array_sum(array_map(fn($r)=>(float)$r->pend,$rows2)) . "\n";
$almDist = [];
foreach ($rows2 as $r) { $a=$r->alm??'NULL'; $almDist[$a]=($almDist[$a]??0)+1; }
echo "Por almacen: "; print_r($almDist);