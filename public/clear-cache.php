<?php
// Solo para desarrollo - eliminar en producción
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Artisan::call('view:clear');
Artisan::call('cache:clear');
Artisan::call('config:clear');

echo "✓ Cache cleared! <a href='javascript:history.back()'>← Volver</a>";
