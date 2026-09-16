<?php
$dir = 'app/Filament/';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;

    $relativePath = str_replace('\\', '/', $file->getPathname());
    $pos = strpos($relativePath, 'app/Filament/');
    if ($pos === false) continue;
    $relativePath = substr($relativePath, $pos);

    // Compute expected namespace from path (dir portion)
    $dirPart = dirname($relativePath); // e.g. app/Filament/Resources/MentorshipResource/Pages
    $expectedNs = str_replace('/', '\\', $dirPart);
    $expectedNs = 'App\\' . substr($expectedNs, 4); // replace 'app\' with 'App\'

    $content = file_get_contents($file->getPathname());
    if (preg_match('/^namespace\s+([^;]+);/m', $content, $m)) {
        $actualNs = trim($m[1]);
        if ($actualNs !== $expectedNs) {
            echo "MISMATCH: " . basename($file->getPathname()) . "\n";
            echo "  Expected: " . $expectedNs . "\n";
            echo "  Actual:   " . $actualNs . "\n";
        }
    }
}
echo "Done scanning\n";
