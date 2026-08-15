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

$sql = "SELECT p.proname AS name, pg_get_function_result(p.oid) AS result, pg_get_function_identity_arguments(p.oid) AS args " .
       "FROM pg_proc p JOIN pg_namespace n ON p.pronamespace=n.oid " .
       "WHERE n.nspname='public' AND p.prokind='f' " .
       "AND (p.proname LIKE '%purchases%' OR p.proname LIKE '%payables%' OR p.proname LIKE '%compras%') " .
       "ORDER BY p.proname;";

foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("%-45s %-60s %s\n", $r['name'], $r['args'], $r['result']);
}
