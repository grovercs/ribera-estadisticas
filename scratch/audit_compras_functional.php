<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$host = $_ENV['SUPABASE_DB_HOST'] ?? '';
$port = $_ENV['SUPABASE_DB_PORT'] ?? '5432';
$db   = $_ENV['SUPABASE_DB_DATABASE'] ?? '';
$user = $_ENV['SUPABASE_DB_USERNAME'] ?? '';
$pass = $_ENV['SUPABASE_DB_PASSWORD'] ?? '';

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db;sslmode=require", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$year = 2025;
$month = 7;

function call($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$tests = [
    ['KPIs', 'SELECT * FROM get_purchases_kpis(:y, :m)', ['y' => $year, 'm' => $month]],
    ['Evolución', 'SELECT * FROM get_purchases_evolution()', []],
    ['Por almacén', 'SELECT * FROM get_purchases_by_warehouse(:y)', ['y' => $year]],
    ['Top familias', 'SELECT * FROM get_purchases_top_families(:limit, :y)', ['limit' => 10, 'y' => $year]],
    ['Top proveedores', 'SELECT * FROM get_purchases_top_suppliers(:limit, :y)', ['limit' => 10, 'y' => $year]],
    ['Resumen impositivo', 'SELECT * FROM get_purchases_tax_summary(:y)', ['y' => $year]],
    ['Payables store', 'SELECT * FROM get_store_dashboard_payables()', []],
    ['Purchases periods store', 'SELECT * FROM get_store_dashboard_purchases_periods(:y)', ['y' => $year]],
];

foreach ($tests as [$name, $sql, $params]) {
    $res = call($pdo, $sql, $params);
    echo "=== $name ===\n";
    if (!$res['ok']) {
        echo "ERROR: " . $res['error'] . "\n\n";
        continue;
    }
    $rows = $res['rows'];
    echo "Filas: " . count($rows) . "\n";
    if (count($rows) === 0) {
        echo "(sin filas)\n\n";
        continue;
    }
    if (count($rows) === 1 && isset($rows[0]['get_purchases_tax_summary'])) {
        echo json_encode($rows[0]['get_purchases_tax_summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } elseif (count($rows) === 1 && isset($rows[0]['get_store_dashboard_payables'])) {
        echo json_encode($rows[0]['get_store_dashboard_payables'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } elseif (count($rows) === 1 && isset($rows[0]['get_store_dashboard_purchases_periods'])) {
        echo json_encode($rows[0]['get_store_dashboard_purchases_periods'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        $first = array_slice($rows, 0, 3);
        foreach ($first as $r) {
            echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "\n";
}
