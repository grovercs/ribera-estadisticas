<?php
// Proceso A: limpia cache y lo repuebla (como haria una peticion).
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
cache()->flush();
use Illuminate\Http\Request;
$ctrl = app(App\Http\Controllers\FinancialController::class);
$ctrl->index(Request::create('/financial', 'GET')); // pobla cache
echo "Proceso A: cache poblado.\n";