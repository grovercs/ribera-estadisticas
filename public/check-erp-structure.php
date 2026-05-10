<?php
// Verificar estructura del ERP
try {
    $erp = new PDO(
        "sqlsrv:Server=192.168.200.105,1433;Database=INTEGRAL",
        'sa',
        '63024',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>Estructura del ERP SQL Server</h2><pre>";

    // Tablas principales
    $tables = ['articulos', 'familias', 'subfamilias', 'clientes', 'hist_ventas_cabecera', 'hist_ventas_linea'];

    foreach ($tables as $table) {
        echo "\n=== $table ===\n";
        $stmt = $erp->query("SELECT TOP 1 * FROM $table");
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $col = $stmt->getColumnMeta($i);
            echo "  - {$col['name']}\n";
        }
    }

    // Verificar si subfamilias tiene cod_familia
    echo "\n=== subfamilias (estructura completa) ===\n";
    $stmt = $erp->query("
        SELECT COLUMN_NAME, DATA_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'subfamilias'
        ORDER BY ORDINAL_POSITION
    ");
    foreach ($stmt->fetchAll() as $col) {
        echo "  - {$col['COLUMN_NAME']} ({$col['DATA_TYPE']})\n";
    }

    echo "</pre>";

} catch (PDOException $e) {
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
