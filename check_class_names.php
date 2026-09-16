<?php
$dir = 'app/Filament/';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;

    $expectedClass = $file->getBasename('.php');
    $content = file_get_contents($file->getPathname());

    // Extract class name
    if (preg_match('/^(?:abstract\s+)?class\s+(\w+)/m', $content, $m)) {
        $actualClass = $m[1];
        if ($actualClass !== $expectedClass) {
            $relativePath = str_replace('\\', '/', $file->getPathname());
            $pos = strpos($relativePath, 'app/Filament/');
            $shortPath = $pos !== false ? substr($relativePath, $pos) : $relativePath;
            echo "MISMATCH: " . $shortPath . "\n";
            echo "  Expected class: " . $expectedClass . "\n";
            echo "  Actual class:   " . $actualClass . "\n";
        }
    }
}
echo "Done scanning\n";
