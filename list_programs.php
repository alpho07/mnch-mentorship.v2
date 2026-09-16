<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\Program::with('programModules')->orderBy('id')->get() as $p) {
    echo $p->id . ': ' . $p->name . PHP_EOL;
    foreach ($p->programModules()->orderBy('order_sequence')->orderBy('name')->get() as $m) {
        echo '  [' . $m->id . '] ' . $m->name . PHP_EOL;
    }
}
