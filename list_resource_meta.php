<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== RESOURCE CATEGORIES ===\n";
foreach (\App\Models\ResourceCategory::orderBy('id')->get() as $c) {
    echo $c->id . ': ' . $c->name . PHP_EOL;
}

echo "\n=== RESOURCE TYPES ===\n";
foreach (\App\Models\ResourceType::orderBy('id')->get() as $t) {
    echo $t->id . ': ' . $t->name . ' [' . $t->slug . ']' . PHP_EOL;
}

echo "\n=== SUPER ADMIN USER ===\n";
$admin = \App\Models\User::role('super_admin')->first();
echo ($admin ? $admin->id . ': ' . $admin->name : 'none') . PHP_EOL;
