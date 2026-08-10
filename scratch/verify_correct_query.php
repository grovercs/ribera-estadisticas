<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$erp = DB::connection('erp');

$esperado = ['Mes Actual'=>343230.92,'Mes Siguiente'=>306844.06,'En 2 meses'=>102659.03,'En 3 meses'=>21611.53];
$espTotal = 774345.54;
$caseCal = "CASE
    WHEN v.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
    WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,1,GETDATE())) THEN 'Mes Siguiente'
    WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,2,GETDATE())) THEN 'En 2 meses'
    WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE())) THEN 'En 3 meses'
    ELSE 'Mas de 3 meses' END";

// Query FINAL: neto (<>0), excluye confirming, desde dia 1 del mes, tope 3 meses
$res = $erp->select("SELECT $caseCal as periodo, SUM(v.importe - v.importe_pagado) as importe
    FROM vencimientos_facturas_compras v
    WHERE (v.importe - v.importe_pagado) <> 0
      AND v.fecha_vencimiento IS NOT NULL
      AND (v.cod_confirming IS NULL OR v.cod_confirming='')
      AND v.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1)
      AND v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE()))
    GROUP BY $caseCal");

echo "=== QUERY FINAL (neto <>0, sin confirming, dia1..+3m) ===\n";
$tot=0; $allOk=true;
foreach($esperado as $k=>$v){
    $f=null;foreach($res as $r){if(($r->periodo??null)===$k){$f=$r->importe;break;}}
    $d=$f!==null?$f-$v:null;
    $ok=($d!==null&&abs($d)<0.5);
    if(!$ok)$allOk=false;
    printf("  %-15s esp=%11.2f obt=%s diff=%s %s\n",$k,$v,$f!==null?sprintf("%11.2f",$f):"  (nada)  ",$d!==null?sprintf("%+8.2f",$d):"   -   ",$ok?'OK':'XX');
    if($f!==null)$tot+=$f;
}
foreach($res as $r){if(!array_key_exists($r->periodo,$esperado)){printf("  %-15s (extra)=%11.2f\n",$r->periodo,$r->importe);}}
printf("  TOTAL obt=%11.2f esp=%11.2f diff=%+8.2f %s\n",$tot,$espTotal,$tot-$espTotal,abs($tot-$espTotal)<0.5?'OK':'XX');
echo $allOk && abs($tot-$espTotal)<0.5 ? "\n>>> COINCIDENCIA TOTAL <<<\n" : "\n>>> aun difiere <<<\n";