<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$erp = DB::connection('erp');

$esperado = ['Mes Actual'=>343230.92,'Mes Siguiente'=>306844.06,'En 2 meses'=>102659.03,'En 3 meses'=>21611.53];
$espTotal = 774345.54;

function show($titulo, $res, $esperado, $espTotal) {
    echo "--- $titulo ---\n";
    $tot=0;
    foreach ($esperado as $k=>$v){
        $f=null; foreach($res as $r){ if(($r->periodo??null)===$k){$f=$r->importe;break;} }
        $d=$f!==null?$f-$v:null;
        printf("  %-15s esp=%11.2f obt=%s diff=%s %s\n",$k,$v,$f!==null?sprintf("%11.2f",$f):"  (nada)  ",$d!==null?sprintf("%+8.2f",$d):"   -   ",($d!==null&&abs($d)<1)?'OK':'XX');
        if($f!==null)$tot+=$f;
    }
    foreach($res as $r){ if(!array_key_exists($r->periodo,$esperado)){ printf("  %-15s (extra) obt=%11.2f\n",$r->periodo,$r->importe); $tot+=$r->importe; } }
    printf("  TOTAL obt=%11.2f esp=%11.2f diff=%+8.2f %s\n\n",$tot,$espTotal,$tot-$espTotal,abs($tot-$espTotal)<1?'OK':'XX');
}

// Bucketing por mes natural: Mes Actual = desde dia 1 del mes en curso
$caseCal = "
    CASE
        WHEN v.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
        WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,1,GETDATE())) THEN 'Mes Siguiente'
        WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,2,GETDATE())) THEN 'En 2 meses'
        WHEN v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE())) THEN 'En 3 meses'
        ELSE 'Mas de 3 meses'
    END
";

$tablas = [
    'vencimientos_facturas (cobros, importe_cobrado)' => ['vencimientos_facturas','importe_cobrado'],
    'vencimientos_facturas_compras (pagos, importe_pagado)' => ['vencimientos_facturas_compras','importe_pagado'],
];

foreach ($tablas as $label=>$t) {
    $tbl=$t[0]; $pag=$t[1];
    // B1: pendiente>0, desde dia 1 del mes en curso (excluye vencidos antes de este mes), sin tope
    echo "\n=== $label | B1: pend>0, fecha>=startofmonth, sin tope ===\n";
    try {
        $res=$erp->select("SELECT $caseCal as periodo, SUM(v.importe - v.$pag) as importe FROM $tbl v WHERE (v.importe - v.$pag)>0 AND v.fecha_vencimiento IS NOT NULL AND v.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1) GROUP BY $caseCal");
        show('B1',$res,$esperado,$espTotal);
    } catch(\Exception $e){echo "  ERR: ".$e->getMessage()."\n";}

    // B2: pendiente>0, desde dia 1 mes en curso, tope 3 meses (excluye Mas de 3 meses)
    echo "\n=== $label | B2: pend>0, startofmonth..EOMONTH(+3) ===\n";
    try {
        $res=$erp->select("SELECT $caseCal as periodo, SUM(v.importe - v.$pag) as importe FROM $tbl v WHERE (v.importe - v.$pag)>0 AND v.fecha_vencimiento IS NOT NULL AND v.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1) AND v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE())) GROUP BY $caseCal");
        show('B2',$res,$esperado,$espTotal);
    } catch(\Exception $e){echo "  ERR: ".$e->getMessage()."\n";}

    // B3: importe total (sin restar pagado), desde dia 1, tope 3 meses
    echo "\n=== $label | B3: importe TOTAL, startofmonth..EOMONTH(+3) ===\n";
    try {
        $res=$erp->select("SELECT $caseCal as periodo, SUM(v.importe) as importe FROM $tbl v WHERE v.importe>0 AND v.fecha_vencimiento IS NOT NULL AND v.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1) AND v.fecha_vencimiento <= EOMONTH(DATEADD(MONTH,3,GETDATE())) GROUP BY $caseCal");
        show('B3',$res,$esperado,$espTotal);
    } catch(\Exception $e){echo "  ERR: ".$e->getMessage()."\n";}
}

echo "Esperado: Mes Actual=343230.92 | Mes Siguiente=306844.06 | En 2 meses=102659.03 | En 3 meses=21611.53 | TOTAL=774345.54\n";