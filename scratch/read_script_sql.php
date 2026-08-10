<?php
$content = file_get_contents(__DIR__ . '/../imagenes/script.sql');

// Check if UTF-16
if (substr($content, 0, 2) === "\xFF\xFE" || substr($content, 0, 2) === "\xFE\xFF") {
    echo "Encoding: UTF-16 detected\n";
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
}

echo "Total length: " . strlen($content) . " chars\n";

$keywords = ['vencimientos_facturas', 'devoluciones', 'impagados', 'pendientes'];
foreach ($keywords as $kw) {
    $pos = 0;
    $count = 0;
    while (($pos = stripos($content, $kw, $pos)) !== false) {
        $count++;
        // Print surrounding context
        $start = max(0, $pos - 100);
        $len = min(strlen($content) - $start, 250);
        echo "Match $count for '$kw' at position $pos:\n";
        echo "----------------------------------------\n";
        echo substr($content, $start, $len) . "\n";
        echo "----------------------------------------\n\n";
        $pos += strlen($kw);
        if ($count >= 5) {
            echo "... more matches omitted ...\n\n";
            break;
        }
    }
}
