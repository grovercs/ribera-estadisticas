<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=ribera_estadisticas;charset=utf8mb4',
    'root',
    ''
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>Estructura MySQL Local</h2><pre>";

$tables = ['erp_products', 'erp_sales', 'erp_clients', 'erp_families', 'erp_subfamilies'];

foreach ($tables as $table) {
    echo "\n=== $table ===\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } catch (PDOException $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
