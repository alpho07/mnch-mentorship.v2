<?php
// Trace when FulfillStockRequest is loaded
$origInclude = stream_get_wrappers();

// Use a simple trick - intercept class declarations by checking included files
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

define('LARAVEL_START', microtime(true));
require 'vendor/autoload.php';

// Track file inclusions
$includedFiles = [];
register_shutdown_function(function() use (&$includedFiles) {
    echo "Files loaded containing 'FulfillStockRequest':\n";
    foreach (get_included_files() as $f) {
        if (strpos($f, 'FulfillStockRequest') !== false) {
            echo "  " . $f . "\n";
        }
    }
});

try {
    $app = require 'bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "Bootstrapped OK\n";

    // Now trigger the panel boot
    \Filament\Facades\Filament::boot();
    echo "Filament boot OK\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . " line " . $e->getLine() . "\n";
    echo "\nLoaded files with FulfillStockRequest:\n";
    foreach (get_included_files() as $f) {
        if (strpos($f, 'FulfillStockRequest') !== false) {
            echo "  " . $f . "\n";
        }
    }
}
