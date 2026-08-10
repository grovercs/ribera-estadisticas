<?php
$f = fopen(__DIR__ . '/../imagenes/rpt_export.csv', 'r');
if (!$f) {
    echo "Could not open rpt_export.csv\n";
    exit;
}

echo "=== FIRST 50 LINES OF rpt_export.csv ===\n";
for ($i = 0; $i < 50; $i++) {
    $line = fgets($f);
    if ($line === false) break;
    echo $line;
}
fclose($f);
