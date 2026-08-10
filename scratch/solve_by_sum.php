<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== SOLVING STORE 2 GROUPS BY TARGET SUM: 343,233.17 ===\n";

// Get all pending vencimientos for Store 2
$vencimientos = $db->select("
    SELECT 
        v.cod_forma_liquidacion,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) > 0
      AND f.cod_almacen = 2
");

// Group by payment form
$groups = [];
foreach ($vencimientos as $v) {
    $fp = $v->cod_forma_liquidacion;
    if (!isset($groups[$fp])) {
        $groups[$fp] = ['count' => 0, 'sum' => 0.0];
    }
    $groups[$fp]['count']++;
    $groups[$fp]['sum'] += (double)$v->pendiente;
}

$keys = array_keys($groups);
$n = count($keys);
echo "Total groups: $n\n";

// Sort by sum descending
usort($keys, function($a, $b) use ($groups) {
    return $groups[$b]['sum'] <=> $groups[$a]['sum'];
});

$targetSum = 343233.17;
$tolerance = 100.0;
$results = [];

$search = function($index, $currentKeys, $currentCount, $currentSum) use (&$search, &$results, $keys, $n, $groups, $targetSum, $tolerance) {
    if (abs($currentSum - $targetSum) <= $tolerance) {
        $results[] = [
            'keys' => $currentKeys,
            'count' => $currentCount,
            'sum' => $currentSum,
            'diff' => abs($currentSum - $targetSum)
        ];
    }
    
    if ($index >= $n) {
        return;
    }
    
    // Since sums can be negative (credits), we don't prune purely by exceeding targetSum,
    // but we can prune if the sum is way too high and remaining positive sums can't bring it down.
    
    // Include
    $key = $keys[$index];
    $newKeys = $currentKeys;
    $newKeys[] = $key;
    $search($index + 1, $newKeys, $currentCount + $groups[$key]['count'], $currentSum + $groups[$key]['sum']);
    
    // Exclude
    $search($index + 1, $currentKeys, $currentCount, $currentSum);
};

$search(0, [], 0, 0.0);

echo "Found " . count($results) . " matches within tolerance.\n";
usort($results, fn($a, $b) => $a['diff'] <=> $b['diff']);
foreach (array_slice($results, 0, 20) as $m) {
    echo sprintf("  Sum: %.2f | Count: %d | Diff: %.2f | Keys: %s\n",
        $m['sum'], $m['count'], $m['diff'], implode(', ', $m['keys'])
    );
}
