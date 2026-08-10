<?php
$output = shell_exec('git show d2a1490 -- app/Http/Controllers/StoreDashboardController.php');

$lines = explode("\n", $output);
foreach ($lines as $i => $line) {
    if (preg_match('/\$impagados\s*=/i', $line) || preg_match('/\$pendientes\s*=/i', $line)) {
        echo "Line $i: $line\n";
        for ($j = max(0, $i - 10); $j <= min(count($lines) - 1, $i + 15); $j++) {
            echo "   " . ($j - $i) . ": " . $lines[$j] . "\n";
        }
        echo "----------------------------------------\n";
    }
}
