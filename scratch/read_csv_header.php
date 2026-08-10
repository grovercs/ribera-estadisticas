<?php
$file = __DIR__ . '/../imagenes/rpt_export.csv';
if (!file_exists($file)) {
    echo "File does not exist: $file\n";
    exit;
}

$handle = fopen($file, 'r');
if ($handle) {
    for ($i = 0; $i < 100; $i++) {
        $line = fgets($handle);
        if ($line === false) {
            break;
        }
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
    fclose($handle);
} else {
    echo "Could not open file\n";
}
