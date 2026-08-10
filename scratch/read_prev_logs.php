<?php
$file = 'C:/Users/Estacion/.gemini/antigravity/brain/f0c7b100-dd95-41d4-a60b-a54589816cfb/.system_generated/logs/overview.txt';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, '11.416,01') !== false || strpos($line, '11416,01') !== false || strpos($line, '343.233') !== false) {
            echo "--- Match at Line $i ---\n";
            for ($j = max(0, $i - 15); $j <= min(count($lines)-1, $i + 35); $j++) {
                echo "$j: " . $lines[$j] . "\n";
            }
            echo "\n-----------------------\n";
        }
    }
} else {
    echo "Logs file not found at $file\n";
}
