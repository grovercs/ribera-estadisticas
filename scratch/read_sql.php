<?php
$file = 'c:\\wamp64\\www\\ribera-estadisticas\\imagenes\\script.sql';
$content = file_get_contents($file);
$content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
$lines = explode("\n", $content);

echo "Table definitions found:\n";
foreach ($lines as $i => $line) {
    if (str_contains($line, 'CREATE TABLE')) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}
