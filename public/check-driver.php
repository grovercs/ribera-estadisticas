<?php
echo "<h2>Verificación de drivers SQL Server</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "PHP Binary: " . PHP_BINARY . "\n\n";

echo "Extensiones cargadas:\n";
$exts = get_loaded_extensions();
sort($exts);
foreach ($exts as $ext) {
    if (stripos($ext, 'sqlsrv') !== false) {
        echo "  ✓ $ext\n";
    }
}

echo "\n";

if (!extension_loaded('pdo_sqlsrv')) {
    echo "ERROR: pdo_sqlsrv NO está cargado\n";
} else {
    echo "OK: pdo_sqlsrv está cargado\n";
}

if (!extension_loaded('sqlsrv')) {
    echo "ERROR: sqlsrv NO está cargado\n";
} else {
    echo "OK: sqlsrv está cargado\n";
}

echo "\nphp.ini loaded: " . php_ini_loaded_file() . "\n";

// Test de conexión al ERP
echo "\n--- Test de conexión al ERP ---\n";
try {
    $pdo = new PDO(
        "sqlsrv:Server=192.168.200.105,1433;Database=INTEGRAL",
        'sa',
        '63024',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Conexión exitosa al ERP (192.168.200.105:1433 / INTEGRAL)\n";

    // Verificar tablas históricas
    $stmt = $pdo->query("SELECT TOP 1 name FROM sys.tables ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ SQL Server responde correctamente\n";

} catch (PDOException $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
}

echo "</pre>";
