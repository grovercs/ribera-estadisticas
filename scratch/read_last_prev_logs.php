<?php
$file = 'C:/Users/Estacion/.gemini/antigravity/brain/f0c7b100-dd95-41d4-a60b-a54589816cfb/.system_generated/logs/overview.txt';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $start = max(0, count($lines) - 200);
    echo "=== LAST 200 LINES OF PREVIOUS LOGS ===\n";
    for ($i = $start; $i < count($lines); $i++) {
        echo "$i: " . $lines[$i] . "\n";
    }
} else {
    echo "Logs file not found\n";
}
