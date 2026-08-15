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

function call($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$year = 2025;

$tests = [
    ['Net margins', 'SELECT * FROM get_dashboard_net_margins(2025,1,2025,12)', []],
    ['Store margins year', 'SELECT * FROM get_store_dashboard_margins(:periodo)', ['periodo' => 'year']],
    ['Store margins today', 'SELECT * FROM get_store_dashboard_margins(:periodo)', ['periodo' => 'hoy']],
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
    foreach (array_slice($rows, 0, 3) as $r) {
        echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}
