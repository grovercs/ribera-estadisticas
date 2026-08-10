<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== HIGHLY OPTIMIZED GLOBAL FP SOLVER ===\n";

// Get all pending vencimientos
$vencimientos = $db->select("
    SELECT 
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

// Sort keys by count descending to optimize search
usort($keys, function($a, $b) use ($groups) {
    return $groups[$b]['count'] <=> $groups[$a]['count'];
});

// Precompute suffixes for pruning
$suffixMaxCounts = array_fill(0, $n, 0);
$suffixMaxSums = array_fill(0, $n, 0.0);
$suffixMinSums = array_fill(0, $n, 0.0); // in case of negative sums

$suffixMaxCounts[$n - 1] = $groups[$keys[$n - 1]]['count'];
$suffixMaxSums[$n - 1] = max(0.0, $groups[$keys[$n - 1]]['sum']);
$suffixMinSums[$n - 1] = min(0.0, $groups[$keys[$n - 1]]['sum']);

for ($i = $n - 2; $i >= 0; $i--) {
    $c = $groups[$keys[$i]]['count'];
    $s = $groups[$keys[$i]]['sum'];
    
    $suffixMaxCounts[$i] = $suffixMaxCounts[$i + 1] + $c;
    $suffixMaxSums[$i] = $suffixMaxSums[$i + 1] + ($s > 0 ? $s : 0.0);
    $suffixMinSums[$i] = $suffixMinSums[$i + 1] + ($s < 0 ? $s : 0.0);
}

$targetCount = 706;
$targetSum = 343233.17;
$tolerance = 5000.0; // We can set a wider tolerance first, then print close matches
$results = [];

$search = function($index, $currentKeys, $currentCount, $currentSum) use (&$search, &$results, $keys, $n, $groups, $targetCount, $targetSum, $tolerance, $suffixMaxCounts, $suffixMaxSums, $suffixMinSums) {
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
    
    // Prune by count
    if ($currentCount > $targetCount) {
        return;
    }
    if ($currentCount + $suffixMaxCounts[$index] < $targetCount) {
        return;
    }
    
    // Prune by sum if count is very close
    if ($targetCount - $currentCount <= 10) { // only apply sum pruning when close to count target to avoid issues with negative values
        if ($currentSum + $suffixMinSums[$index] - $targetSum > $tolerance) {
            return;
        }
        if ($currentSum + $suffixMaxSums[$index] - $targetSum < -$tolerance) {
            return;
        }
    }
    
    // Option 1: Include
    $key = $keys[$index];
    $newKeys = $currentKeys;
    $newKeys[] = $key;
    $search($index + 1, $newKeys, $currentCount + $groups[$key]['count'], $currentSum + $groups[$key]['sum']);
    
    // Option 2: Exclude
    $search($index + 1, $currentKeys, $currentCount, $currentSum);
};

$search(0, [], 0, 0.0);

echo "Found " . count($results) . " matches within $tolerance tolerance.\n";
usort($results, fn($a, $b) => $a['diff'] <=> $b['diff']);
foreach (array_slice($results, 0, 20) as $m) {
    echo sprintf("  Sum: %.2f | Diff: %.2f | Keys: %s\n",
        $m['sum'], $m['diff'], implode(', ', $m['keys'])
    );
}
