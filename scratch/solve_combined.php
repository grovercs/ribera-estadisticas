<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('erp');

echo "=== COMBINED SOLVER: Store 1 Base + Store 2 Subset ===\n";

// Get all pending vencimientos for Store 1
$v1 = $db->select("
    SELECT 
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
      AND f.cod_almacen = 1
");

$baseCount = count($v1);
$baseSum = 0.0;
foreach ($v1 as $item) {
    $baseSum += (double)$item->pendiente;
}

echo "Store 1 Base Count: $baseCount | Base Sum: " . number_format($baseSum, 2) . "\n";

$targetCount = 706;
$targetSum = 343233.17;

$neededCount = $targetCount - $baseCount;
$neededSum = $targetSum - $baseSum;

echo "Needed from Store 2: Count = $neededCount | Sum = " . number_format($neededSum, 2) . "\n";

if ($neededCount <= 0) {
    echo "Base count is already larger than or equal to target count.\n";
    exit;
}

// Get all pending vencimientos for Store 2 grouped by FP
$v2 = $db->select("
    SELECT 
        v.cod_forma_liquidacion,
        (v.importe - v.importe_cobrado) as pendiente
    FROM vencimientos_facturas v
    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura 
        AND v.tipo_factura = f.tipo_factura 
        AND v.cod_empresa = f.cod_empresa
    WHERE (v.importe - v.importe_cobrado) <> 0
      AND f.cod_almacen = 2
");

$groups = [];
foreach ($v2 as $item) {
    $fp = $item->cod_forma_liquidacion;
    if (!isset($groups[$fp])) {
        $groups[$fp] = ['count' => 0, 'sum' => 0.0];
    }
    $groups[$fp]['count']++;
    $groups[$fp]['sum'] += (double)$item->pendiente;
}

$keys = array_keys($groups);
$n = count($keys);
echo "Store 2 has $n groups.\n";

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

$tolerance = 50.0;
$results = [];

$search = function($index, $currentKeys, $currentCount, $currentSum) use (&$search, &$results, $keys, $n, $groups, $neededCount, $neededSum, $tolerance, $suffixMaxCounts) {
    if ($currentCount === $neededCount) {
        if (abs($currentSum - $neededSum) <= $tolerance) {
            $results[] = [
                'keys' => $currentKeys,
                'sum' => $currentSum,
                'diff' => abs($currentSum - $neededSum)
            ];
        }
        return;
    }
    
    if ($index >= $n) {
        return;
    }
    
    if ($currentCount > $neededCount) {
        return;
    }
    
    if ($currentCount + $suffixMaxCounts[$index] < $neededCount) {
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
foreach (array_slice($results, 0, 15) as $m) {
    echo sprintf("  Sum: %.2f | Diff: %.2f | Keys: %s\n",
        $m['sum'], $m['diff'], implode(', ', $m['keys'])
    );
}
