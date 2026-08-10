<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== GLOBAL FP SEARCH FOR 706 COUNT / 343233.17 SUM ===\n";

// Get all pending vencimientos
$vencimientos = $db->select("
    SELECT 
        v.cod_factura,
        v.cod_forma_liquidacion,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    WHERE (v.importe - v.importe_cobrado) > 0
");

// Group them by payment form
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
echo "Total global FPs: $n\n";

// Sort by count
usort($keys, function($a, $b) use ($groups) {
    return $groups[$b]['count'] <=> $groups[$a]['count'];
});

// Suffix sums of counts for pruning
$suffixMaxCounts = array_fill(0, $n, 0);
$suffixMaxCounts[$n - 1] = $groups[$keys[$n - 1]]['count'];
for ($i = $n - 2; $i >= 0; $i--) {
    $suffixMaxCounts[$i] = $suffixMaxCounts[$i + 1] + $groups[$keys[$i]]['count'];
}

$targetCount = 706;
$targetSum = 343233.17;
$tolerance = 1000.0;
$results = [];

$search = function($index, $currentKeys, $currentCount, $currentSum) use (&$search, &$results, $keys, $n, $groups, $targetCount, $targetSum, $tolerance, $suffixMaxCounts) {
    if ($currentCount === $targetCount) {
        if (abs($currentSum - $targetSum) <= $tolerance) {
            $results[] = [
                'keys' => $currentKeys,
                'sum' => $currentSum,
                'diff' => abs($currentSum - $targetSum)
            ];
        }
        return;
    }
    
    if ($index >= $n) {
        return;
    }
    
    if ($currentCount > $targetCount) {
        return;
    }
    
    if ($currentCount + $suffixMaxCounts[$index] < $targetCount) {
        return;
    }
    
    // Include
    $key = $keys[$index];
    $newKeys = $currentKeys;
    $newKeys[] = $key;
    $search($index + 1, $newKeys, $currentCount + $groups[$key]['count'], $currentSum + $groups[$key]['sum']);
    
    // Exclude
    $search($index + 1, $currentKeys, $currentCount, $currentSum);
};

$search(0, [], 0, 0.0);

echo "Found " . count($results) . " matches.\n";
usort($results, fn($a, $b) => $a['diff'] <=> $b['diff']);
foreach (array_slice($results, 0, 10) as $m) {
    echo sprintf("  Sum: %.2f | Diff: %.2f | Keys: %s\n",
        $m['sum'], $m['diff'], implode(', ', $m['keys'])
    );
}
