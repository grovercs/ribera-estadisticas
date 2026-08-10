<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== SOLVING FOR TARGET COUNT: 706, SUM: 343,233.17 ===\n";

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

$store2Groups = [];
$allGroups = [];
foreach ($groups as $k => $g) {
    if (strpos($k, '2_') === 0) {
        $store2Groups[$k] = $g;
    }
    $allGroups[$k] = $g;
}

function findSubsetsOptimized($groups, $targetCount, $targetSum, $tolerance) {
    $keys = array_keys($groups);
    $n = count($keys);
    
    // Sort groups by count descending to prune branches early
    usort($keys, function($a, $b) use ($groups) {
        return $groups[$b]['count'] <=> $groups[$a]['count'];
    });
    
    // Precompute suffix sums of counts to prune branches that cannot reach target
    $suffixMaxCounts = array_fill(0, $n, 0);
    $suffixMaxCounts[$n - 1] = $groups[$keys[$n - 1]]['count'];
    for ($i = $n - 2; $i >= 0; $i--) {
        $suffixMaxCounts[$i] = $suffixMaxCounts[$i + 1] + $groups[$keys[$i]]['count'];
    }
    
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
        
        // Prune if current count exceeds target
        if ($currentCount > $targetCount) {
            return;
        }
        
        // Prune if remaining items cannot reach target count
        if ($currentCount + $suffixMaxCounts[$index] < $targetCount) {
            return;
        }
        
        // Option 1: Include group at index
        $key = $keys[$index];
        $newKeys = $currentKeys;
        $newKeys[] = $key;
        $search($index + 1, $newKeys, $currentCount + $groups[$key]['count'], $currentSum + $groups[$key]['sum']);
        
        // Option 2: Exclude group at index
        $search($index + 1, $currentKeys, $currentCount, $currentSum);
    };
    
    $search(0, [], 0, 0.0);
    
    usort($results, fn($a, $b) => $a['diff'] <=> $b['diff']);
    return $results;
}

echo "\n--- SEARCHING IN STORE 2 ONLY GROUPS ---\n";
$matches2 = findSubsetsOptimized($store2Groups, 706, 343233.17, 1000.0);
echo "Found " . count($matches2) . " matches.\n";
foreach (array_slice($matches2, 0, 10) as $m) {
    echo sprintf("  Sum: %.2f | Diff: %.2f | Keys: %s\n",
        $m['sum'], $m['diff'], implode(', ', $m['keys'])
    );
}

echo "\n--- SEARCHING IN ALL STORES GROUPS ---\n";
$matchesAll = findSubsetsOptimized($allGroups, 706, 343233.17, 10.0);
echo "Found " . count($matchesAll) . " matches.\n";
foreach (array_slice($matchesAll, 0, 15) as $m) {
    echo sprintf("  Sum: %.2f | Diff: %.2f | Keys: %s\n",
        $m['sum'], $m['diff'], implode(', ', $m['keys'])
    );
}
