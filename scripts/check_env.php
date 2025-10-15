<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo "SUPERADMIN_EMAIL=" . (env('SUPERADMIN_EMAIL') ?? 'NULL') . "\n";
echo "SUPERADMIN_PASSWORD=" . (env('SUPERADMIN_PASSWORD') ?? 'NULL') . "\n";
