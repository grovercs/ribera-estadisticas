<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=ribera_estadisticas;charset=utf8mb4',
    'root',
    ''
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ver años disponibles en erp_sales
$stmt = $pdo->query("
    SELECT DISTINCT YEAR(fecha_venta) as year
    FROM erp_sales
    WHERE fecha_venta IS NOT NULL
    ORDER BY year DESC
");
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<pre>";
echo "Años disponibles en erp_sales:\n";
if (empty($years)) {
    echo "  (no hay datos)\n";
} else {
    foreach ($years as $y) {
        $count = $pdo->query("SELECT COUNT(*) FROM erp_sales WHERE YEAR(fecha_venta) = $y")->fetchColumn();
        echo "  - $y ($count ventas)\n";
    }
}

echo "\n";
echo "Rango: " . (min($years) ?? 'N/A') . " - " . (max($years) ?? 'N/A');
echo "</pre>";
