<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$cases = [
  ['familias','/financial/detalle-familias',['year_from'=>2026,'year_to'=>2026,'sort'=>'margin_rate','dir'=>'asc','page'=>2,'search'=>'TEJ']],
  ['productos','/financial/detalle-productos',['year_from'=>2024,'year_to'=>2026,'sort'=>'total_qty','dir'=>'desc','page'=>3,'search'=>'']],
  ['clientes','/financial/detalle-clientes',['year_from'=>2026,'year_to'=>2026,'sort'=>'revenue','dir'=>'desc','page'=>1,'search'=>'BARC']],
  ['ppv','/financial/detalle-ppv',['year_from'=>2026,'year_to'=>2026,'sort'=>'var','dir'=>'desc','page'=>2,'search'=>'']],
];
foreach ($cases as [$name,$path,$p]) {
  $t=microtime(true);
  try {
    $method=['familias'=>'detalleFamilias','productos'=>'detalleProductos','clientes'=>'detalleClientes','ppv'=>'detallePpv'][$name];
    $view=$ctrl->$method(Request::create($path,'GET',$p));
    $html=$view->render(); $d=$view->getData();
    $first=$d['rows'][0] ?? null;
    printf("[%-9s] page=%d total=%d rows=%d %.2fs sort=%s/%s search=%s\n",
      $name,$d['page'],$d['total'],count($d['rows']),microtime(true)-$t,$d['sort'],$d['dir'],($d['search']?:'-'));
    if ($first) echo "  first: ".json_encode(array_slice($first,0,4,true),JSON_UNESCAPED_UNICODE)."\n";
  } catch (\Throwable $e) { echo "[$name] ERROR ".$e->getMessage()." @ ".$e->getLine()."\n"; }
}
