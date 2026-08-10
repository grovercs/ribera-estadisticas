<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== HEURISTIC SEARCH FOR 706 COUNT / 343233.17 SUM ===\n";

// Get all pending vencimientos
$vencimientos = $db->select("
    SELECT 
        v.cod_factura,
        v.cod_forma_liquidacion,
        v.emitido,
        v.fecha_vencimiento,
        f.cod_almacen,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
");

// Group them by store and payment form
$groups = [];
foreach ($vencimientos as $v) {
    $store = $v->cod_almacen ?? 0;
    $fp = $v->cod_forma_liquidacion;
    $key = "{$store}_{$fp}";
    if (!isset($groups[$key])) {
        $groups[$key] = ['count' => 0, 'sum' => 0.0];
    }
    $groups[$key]['count']++;
    $groups[$key]['sum'] += (double)$v->pendiente;
}

$groupKeys = array_keys($groups);
$n = count($groupKeys);

// We want to find a binary vector of length $n that has sum of counts = 706, and sum of values = 343233.17
$targetCount = 706;
$targetSum = 343233.17;

echo "Running hill climbing...\n";
$bestDiff = 1e9;
$bestSolution = null;

for ($run = 0; $run < 100; $run++) {
    // Generate a random solution that is close to the target count
    $sol = array_fill(0, $n, 0);
    $currentCount = 0;
    $currentSum = 0.0;
    
    // Shuffle keys for randomness
    $indices = range(0, $n - 1);
    shuffle($indices);
    
    foreach ($indices as $idx) {
        $key = $groupKeys[$idx];
        if ($currentCount + $groups[$key]['count'] <= $targetCount + 50) {
            $sol[$idx] = 1;
            $currentCount += $groups[$key]['count'];
            $currentSum += $groups[$key]['sum'];
        }
    }
    
    // Hill climbing step
    $improved = true;
    $iterations = 0;
    while ($improved && $iterations < 1000) {
        $improved = false;
        $iterations++;
        
        // Try toggling each bit
        for ($i = 0; $i < $n; $i++) {
            $key = $groupKeys[$i];
            $newSol = $sol;
            $newSol[$i] = 1 - $sol[$i];
            
            $newCount = 0;
            $newSum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                if ($newSol[$j] === 1) {
                    $newCount += $groups[$groupKeys[$j]]['count'];
                    $newSum += $groups[$groupKeys[$j]]['sum'];
                }
            }
            
            // Check if this new solution is better
            // We prioritize matching the count exactly, and then minimizing sum diff
            $oldPenalty = ($currentCount === $targetCount) ? abs($currentSum - $targetSum) : 1e9 + abs($currentCount - $targetCount) * 100000;
            $newPenalty = ($newCount === $targetCount) ? abs($newSum - $targetSum) : 1e9 + abs($newCount - $targetCount) * 100000;
            
            if ($newPenalty < $oldPenalty) {
                $sol = $newSol;
                $currentCount = $newCount;
                $currentSum = $newSum;
                $improved = true;
            }
        }
    }
    
    $diff = abs($currentSum - $targetSum);
    if ($currentCount === $targetCount && $diff < $bestDiff) {
        $bestDiff = $diff;
        $bestSolution = $sol;
        echo "Run $run | Found match with Count = $currentCount, Sum = " . number_format($currentSum, 2) . " (Diff: " . number_format($diff, 2) . ")\n";
        if ($diff < 1.0) {
            break;
        }
    }
}

if ($bestSolution !== null) {
    echo "\n=== BEST SOLUTION FOUND ===\n";
    $included = [];
    $newCount = 0;
    $newSum = 0.0;
    for ($i = 0; $i < $n; $i++) {
        if ($bestSolution[$i] === 1) {
            $key = $groupKeys[$i];
            $included[] = $key;
            $newCount += $groups[$key]['count'];
            $newSum += $groups[$key]['sum'];
            echo "  Group: $key | Count: {$groups[$key]['count']} | Sum: " . number_format($groups[$key]['sum'], 2) . "\n";
        }
    }
    echo "Total Count: $newCount | Total Sum: " . number_format($newSum, 2) . "\n";
    echo "Keys: " . implode(', ', $included) . "\n";
} else {
    echo "\nNo solution found with exact count $targetCount.\n";
}
