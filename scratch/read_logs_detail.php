<?php
$file = 'C:/Users/Estacion/.gemini/antigravity/brain/bef8b880-dab2-4944-8247-c8265a76f075/.system_generated/logs/overview.txt';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    // Let's find where 11.416,01 or 11416,01 is mentioned, and print 100 lines around it
    foreach ($lines as $i => $line) {
        if (strpos($line, '11.416,01') !== false || strpos($line, '11416,01') !== false) {
            echo "--- Match at Line $i ---\n";
            for ($j = max(0, $i - 40); $j <= min(count($lines)-1, $i + 40); $j++) {
                echo "$j: " . $lines[$j] . "\n";
            }
            echo "\n-----------------------\n";
        }
    }
} else {
    echo "Logs file not found at $file\n";
}
