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
          AND (v.cod_confirming IS NULL OR v.cod_confirming='')
          AND v.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1)
          AND v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE()))
          AND $where GROUP BY $caseCal");
}

// verificado distribution
echo "=== verificado (en Mes Actual, pend>0, no confirming) ===\n";
foreach($erp->select("SELECT CASE WHEN verificado IS NULL OR verificado='' THEN 'NO' ELSE 'SI' END as ver, COUNT(*) c, SUM(importe-importe_pagado) s FROM vencimientos_facturas_compras WHERE (importe-importe_pagado)>0 AND fecha_vencimiento IS NOT NULL AND (cod_confirming IS NULL OR cod_confirming='') AND fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1) AND fecha_vencimiento <= EOMONTH(GETDATE()) GROUP BY CASE WHEN verificado IS NULL OR verificado='' THEN 'NO' ELSE 'SI' END") as $r)
    printf("  verificado=%s  count=%4d  sum=%12.2f\n",$r->ver,$r->c,$r->s);

// numero distribution (numero >1 means partial installments)
echo "\n=== numero (cuota) en Mes Actual ===\n";
foreach($erp->select("SELECT numero, COUNT(*) c, SUM(importe-importe_pagado) s FROM vencimientos_facturas_compras WHERE (importe-importe_pagado)>0 AND fecha_vencimiento IS NOT NULL AND (cod_confirming IS NULL OR cod_confirming='') AND fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1) AND fecha_vencimiento <= EOMONTH(GETDATE()) GROUP BY numero ORDER BY numero") as $r)
    printf("  numero=%s  count=%4d  sum=%12.2f\n",$r->numero,$r->c,$r->s);

// tipo de factura: cod_factura prefix (abonos suelen empezar con 'A' o tener tipo negativo)
echo "\n=== signo de importe en Mes Actual (abonos) ===\n";
foreach($erp->select("SELECT CASE WHEN importe<0 THEN 'NEG' ELSE 'POS' END as sg, COUNT(*) c, SUM(importe-importe_pagado) s FROM vencimientos_facturas_compras WHERE (importe-importe_pagado)<>0 AND fecha_vencimiento IS NOT NULL AND (cod_confirming IS NULL OR cod_confirming='') AND fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1) AND fecha_vencimiento <= EOMONTH(GETDATE()) GROUP BY CASE WHEN importe<0 THEN 'NEG' ELSE 'POS' END") as $r)
    printf("  signo=%s  count=%4d  sum=%12.2f\n",$r->sg,$r->c,$r->s);

echo "\n";
show('excl confirming + solo verificado NO (pendiente de verificar)', q($erp,"(v.verificado IS NULL OR v.verificado='')",$caseCal), $esperado,$espTotal);
show('excl confirming + solo verificado SI', q($erp,"v.verificado IS NOT NULL AND v.verificado<>''",$caseCal), $esperado,$espTotal);
show('excl confirming + solo importe>0 (sin abonos negativos)', q($erp,"v.importe>0",$caseCal), $esperado,$espTotal);
show('excl confirming + solo numero=1', q($erp,"v.numero=1",$caseCal), $esperado,$espTotal);

echo "Esperado: 343230.92 / 306844.06 / 102659.03 / 21611.53 = 774345.54\n";