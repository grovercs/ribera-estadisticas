<?php
function env_value(string $key): string
{
    $f = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($f as $l) {
        if (str_starts_with(trim($l), '#')) continue;
        if (str_contains($l, '=')) {
            [$k, $v] = explode('=', $l, 2);
            if (trim($k) === $key) return trim($v, " \t\n\r\v\0\"");
        }
    }
    return '';
}

$pdo = new PDO('pgsql:host=' . env_value('SUPABASE_DB_HOST') . ';port=' . env_value('SUPABASE_DB_PORT', '5432') . ';dbname=' . env_value('SUPABASE_DB_DATABASE') . ';sslmode=require', env_value('SUPABASE_DB_USERNAME'), env_value('SUPABASE_DB_PASSWORD'));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Top 50 familias por ventas 2026
$top = $pdo->query("SELECT * FROM public.get_dashboard_top_families(2026,1,2026,12) LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$codes = array_column($top, 'cod_familia');

// Buscar nombres reales en stats_historical_families
$in = str_repeat('?,', count($codes) - 1) . '?';
$stmt = $pdo->prepare("SELECT DISTINCT cod_familia, family_name FROM stats_historical_families WHERE cod_familia IN ($in) AND family_name IS NOT NULL AND family_name <> '' AND family_name <> cod_familia");
$stmt->execute($codes);
$names = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $names[$r['cod_familia']] = $r['family_name'];
}

$conNombre = 0;
$sinNombre = 0;
foreach ($top as $row) {
    if (isset($names[$row['cod_familia']])) $conNombre++;
    else $sinNombre++;
}

echo "TOP 50 FAMILIAS VENTAS 2026:\n";
echo "Con nombre disponible: $conNombre\n";
echo "Sin nombre disponible: $sinNombre\n";

// Cobertura total
$all = $pdo->query("SELECT DISTINCT cod_familia FROM stats_historical_families WHERE cod_familia IS NOT NULL AND cod_familia <> ''")->fetchAll(PDO::FETCH_COLUMN);
$named = $pdo->query("SELECT DISTINCT cod_familia FROM stats_historical_families WHERE family_name IS NOT NULL AND family_name <> '' AND family_name <> cod_familia")->fetchAll(PDO::FETCH_COLUMN);
echo "\nTotal cod_familia en stats_historical_families: " . count($all) . "\n";
echo "Con nombre real: " . count($named) . "\n";
$diff = array_diff($all, $named);
echo "Sin nombre real: " . count($diff) . "\n";
if ($diff) echo "Ejemplos: " . implode(', ', array_slice($diff, 0, 10)) . "\n";

// Muestra top 10 con nombres
$header = str_pad('#', 4) . str_pad('CODIGO', 10) . str_pad('NOMBRE REAL', 45) . str_pad('VENTAS', 16, ' ', STR_PAD_LEFT) . "\n";
echo "\n$header" . str_repeat('-', 80) . "\n";
foreach (array_slice($top, 0, 10) as $i => $row) {
    $nombre = $names[$row['cod_familia']] ?? $row['family_name'];
    echo str_pad($i + 1, 4) . str_pad($row['cod_familia'], 10) . str_pad($nombre, 45) . str_pad(number_format($row['total'], 2, ',', '.'), 16, ' ', STR_PAD_LEFT) . "\n";
}
