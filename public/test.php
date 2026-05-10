<?php
// Test rápido para verificar que Laravel funciona
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

echo "<pre>";
echo "APP_URL: " . $_ENV['APP_URL'] . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";

// Test de ruta
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$routes = Route::getRoutes()->getRoutes();
echo "\nRutas registradas:\n";
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'families')) {
        echo "  - " . $route->uri() . " => " . $route->getActionName() . "\n";
    }
}
echo "</pre>";
