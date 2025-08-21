<?php
// Simple script to print latest attachments row as JSON
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
// bootstrap the framework
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Attachment;

$a = Attachment::orderByDesc('id')->first();
echo json_encode($a ? $a->toArray() : null) . PHP_EOL;
