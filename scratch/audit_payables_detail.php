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

$res = $pdo->query("SELECT * FROM get_store_dashboard_payables()")->fetch(PDO::FETCH_ASSOC);
$raw = reset($res);
$data = json_decode($raw, true);
print_r($data);
