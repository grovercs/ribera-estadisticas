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

function show($titulo,$res,$esperado,$espTotal){
    echo "--- $titulo ---\n"; $tot=0;
    foreach($esperado as $k=>$v){$f=null;foreach($res as $r){if(($r->periodo??null)===$k){$f=$r->importe;break;}}$d=$f!==null?$f-$v:null;printf("  %-15s esp=%11.2f obt=%s diff=%s %s\n",$k,$v,$f!==null?sprintf("%11.2f",$f):"  (nada)  ",$d!==null?sprintf("%+8.2f",$d):"   -   ",($d!==null&&abs($d)<1)?'OK':'XX');if($f!==null)$tot+=$f;}
    foreach($res as $r){if(!array_key_exists($r->periodo,$esperado)){printf("  %-15s (extra)=%11.2f\n",$r->periodo,$r->importe);}}
    printf("  TOTAL obt=%11.2f esp=%11.2f diff=%+8.2f %s\n\n",$tot,$espTotal,$tot-$espTotal,abs($tot-$espTotal)<1?'OK':'XX');
}

function q($erp,$where,$caseCal){
    return $erp->select("SELECT $caseCal as periodo, SUM(v.importe - v.importe_pagado) as importe
        FROM vencimientos_facturas_compras v
        WHERE (v.importe - v.importe_pagado) > 0 AND v.fecha_vencimiento IS NOT NULL
          AND v.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1)
          AND v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE()))
          AND $where
        GROUP BY $caseCal");
}

// Distribucion por cod_empresa
echo "=== Desglose por cod_empresa (pendiente total, sin tope) ===\n";
foreach($erp->select("SELECT cod_empresa, COUNT(*) c, SUM(importe-importe_pagado) s FROM vencimientos_facturas_compras WHERE (importe-importe_pagado)>0 AND fecha_vencimiento IS NOT NULL GROUP BY cod_empresa ORDER BY cod_empresa") as $r)
    printf("  empresa=%s  count=%4d  sum=%12.2f\n",$r->cod_empresa,$r->c,$r->s);

// Confirming
echo "\n=== Desglose por cod_confirming (vacío vs no) ===\n";
foreach($erp->select("SELECT CASE WHEN cod_confirming IS NULL OR cod_confirming='' THEN 'NO' ELSE 'SI' END as conf, COUNT(*) c, SUM(importe-importe_pagado) s FROM vencimientos_facturas_compras WHERE (importe-importe_pagado)>0 AND fecha_vencimiento IS NOT NULL GROUP BY CASE WHEN cod_confirming IS NULL OR cod_confirming='' THEN 'NO' ELSE 'SI' END") as $r)
    printf("  confirming=%s  count=%4d  sum=%12.2f\n",$r->conf,$r->c,$r->s);

// Divisa
echo "\n=== Desglose por cod_divisa_activa / cod_divisa ===\n";
foreach($erp->select("SELECT cod_divisa_activa, COUNT(*) c, SUM(importe-importe_pagado) s FROM vencimientos_facturas_compras WHERE (importe-importe_pagado)>0 AND fecha_vencimiento IS NOT NULL GROUP BY cod_divisa_activa") as $r)
    printf("  divisa_activa=%s  count=%4d  sum=%12.2f\n",$r->cod_divisa_activa,$r->c,$r->s);

echo "\n";
show('SIN filtro (base B2)', q($erp,'1=1',$caseCal), $esperado,$espTotal);

// Por cada empresa
foreach([1,2,3,4,5] as $ce){
    $res=q($erp,"v.cod_empresa=$ce",$caseCal);
    show("empresa=$ce (desde dia 1, tope 3m)",$res,$esperado,$espTotal);
}

// Excluir confirming
show('excluyendo confirming (cod_confirming vacío)', q($erp,"(v.cod_confirming IS NULL OR v.cod_confirming='')",$caseCal), $esperado,$espTotal);

// Solo divisa activa EUR
show('solo cod_divisa_activa=EUR', q($erp,"v.cod_divisa_activa='EUR'",$caseCal), $esperado,$espTotal);

echo "Esperado: 343230.92 / 306844.06 / 102659.03 / 21611.53 = 774345.54\n";