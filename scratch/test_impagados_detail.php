<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ctrl = app(App\Http\Controllers\StoreDashboardController::class);
foreach (['impagados' => 'all', 'impagados_devueltos' => 'all', 'impagados_p1' => '1'] as $lbl => $tienda) {
    $tipo = strpos($lbl, 'devueltos') !== false ? 'impagados_devueltos' : 'impagados';
    $req = Illuminate\Http\Request::create('/x', 'GET', ['tipo' => $tipo, 'tienda' => $tienda]);
    $j = json_decode($ctrl->detalleImpagados($req)->getContent(), true);
    $sum = array_sum(array_column($j['data'] ?? [], 'importe_pendiente'));
    printf("%-20s success=%s count=%d sum=%.2f\n", $lbl, $j['success']?'ok':'ERR', count($j['data']??[]), $sum);
    if (!($j['success']??false)) echo "  ERR: ".($j['error']??'')."\n";
}
// per-tienda impagados (ERP)
foreach (['1'=>'Pont','2'=>'Vielha','all'=>'Total'] as $t=>$n) {
    $req = Illuminate\Http\Request::create('/x','GET',['tipo'=>'impagados','tienda'=>$t]);
    $j = json_decode($ctrl->detalleImpagados($req)->getContent(),true);
    $sum=array_sum(array_column($j['data']??[],'importe_pendiente'));
    printf("  impagados ERP %-8s count=%d sum=%.2f\n",$n,count($j['data']??[]),$sum);
}