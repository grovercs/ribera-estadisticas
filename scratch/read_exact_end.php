<?php
$file = 'C:/Users/Estacion/.gemini/antigravity/brain/f0c7b100-dd95-41d4-a60b-a54589816cfb/.system_generated/logs/overview.txt';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $total = count($lines);
    echo "Total lines: $total\n";
    for ($i = max(0, $total - 20); $i < $total; $i++) {
        echo "Line $i: " . $lines[$i] . "\n";
    }
} else {
    echo "File not found\n";
}
