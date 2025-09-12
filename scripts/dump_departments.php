<?php
// scripts/dump_departments.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\Company;

$rows = Department::with('company')->limit(50)->get()->map(function ($d) {
    return [
        'id' => $d->id ?? null,
        'name' => $d->name ?? null,
        'company_id' => $d->company_id ?? ($d->company?->id ?? null),
        'company_name' => $d->company?->name ?? null,
    ];
});

echo json_encode(['count' => $rows->count(), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
