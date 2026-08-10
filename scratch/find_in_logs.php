<?php
$brainDir = 'C:/Users/Estacion/.gemini/antigravity/brain';
if (!is_dir($brainDir)) {
    echo "Brain dir does not exist: $brainDir\n";
    exit;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($brainDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'txt' || $file->getExtension() === 'md') {
        $content = file_get_contents($file->getPathname());
        if (strpos($content, '11416') !== false || strpos($content, '11.416') !== false) {
            echo "Found in file: " . $file->getPathname() . "\n";
            // Print matching line and a few around it
            $lines = explode("\n", $content);
            foreach ($lines as $i => $line) {
                if (strpos($line, '11416') !== false || strpos($line, '11.416') !== false) {
                    echo "  Line $i: $line\n";
                }
            }
        }
    }
}
