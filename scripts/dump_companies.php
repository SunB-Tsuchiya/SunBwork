<?php
// scripts/dump_companies.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;

$companies = Company::with(['departments' => function ($q) {
    $q->orderBy('sort_order');
}])->orderBy('name')->get()->toArray();

echo json_encode(['count' => count($companies), 'companies' => $companies], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
