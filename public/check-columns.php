<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=ribera_estadisticas;charset=utf8mb4',
    'root',
    ''
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<pre>";

// Columnas de erp_products
echo "Columnas en erp_products:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM erp_products");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n";

// Columnas de erp_families
echo "Columnas en erp_families:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM erp_families");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n";

// Columnas de erp_sales
echo "Columnas en erp_sales:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM erp_sales");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "</pre>";
