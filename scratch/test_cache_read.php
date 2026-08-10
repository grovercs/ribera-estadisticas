<?php
// Proceso B (distinto proceso): lee el cache y prueba acceder a propiedades stdClass.
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$key = 'financial_data_' . date('Y') . '_' . date('Y') . '_all_all';
$data = cache()->get($key);
if (!$data) { echo "cache miss\n"; exit; }
echo "cache hit. claves: " . implode(',', array_keys($data)) . "\n";
$fams = $data['marginByFamily'] ?? [];
echo "marginByFamily count=" . count($fams) . "\n";
if ($fams) {
    $first = $fams[0];
    echo "tipo primer elemento: " . get_class($first) . "\n";
    $props = (array)$first;
    echo "props keys: " . implode(',', array_keys($props)) . "\n";
    try { echo "acceso ->familia: " . $first->familia . "\n"; echo "OK acceso propiedad\n"; }
    catch (\Throwable $e) { echo "FAIL acceso: " . $e->getMessage() . "\n"; }
}