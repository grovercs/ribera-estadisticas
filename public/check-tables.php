<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=ribera_estadisticas;charset=utf8mb4',
    'root',
    ''
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<pre>";
echo "Tablas en MySQL local:\n";
foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo "  - $table ($count registros)\n";
}

echo "\n\nBuscando tablas de clientes...\n";
$clientTables = array_filter($tables, fn($t) => stripos($t, 'client') !== false);
foreach ($clientTables as $t) {
    echo "  - $t\n";
}
echo "</pre>";
