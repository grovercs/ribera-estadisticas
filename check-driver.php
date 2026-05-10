<?php
// Script directo sin pasar por Laravel
echo "<h2>Verificación de drivers SQL Server - Apache</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n\n";

echo "Extensiones cargadas:\n";
$exts = get_loaded_extensions();
sort($exts);
$found = false;
foreach ($exts as $ext) {
    if (stripos($ext, 'sqlsrv') !== false) {
        echo "  ✓ $ext\n";
        $found = true;
    }
}

if (!$found) {
    echo "  ✗ No se encontraron extensiones sqlsrv\n";
}

echo "\n";
echo "pdo_sqlsrv cargado: " . (extension_loaded('pdo_sqlsrv') ? 'SI' : 'NO') . "\n";
echo "sqlsrv cargado: " . (extension_loaded('sqlsrv') ? 'SI' : 'NO') . "\n";
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

    $stmt = $pdo->query("SELECT TOP 5 name FROM sys.tables ORDER BY name");
    echo "✓ Tablas en SQL Server:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        echo "    - $table\n";
    }

} catch (PDOException $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>";
