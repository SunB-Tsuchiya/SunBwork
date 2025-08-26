<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create 3 PoC ProjectSchedule records
use App\Models\ProjectSchedule;
use Carbon\Carbon;

$now = Carbon::now();
$a = ProjectSchedule::create([
    'name' => 'PoC Task A',
    'start_date' => $now->toDateString(),
    'end_date' => $now->copy()->addDays(2)->toDateString(),
    'progress' => 20,
]);
$b = ProjectSchedule::create([
    'name' => 'PoC Task B',
    'start_date' => $now->copy()->addDays(3)->toDateString(),
    'end_date' => $now->copy()->addDays(7)->toDateString(),
    'progress' => 50,
]);
$c = ProjectSchedule::create([
    'name' => 'PoC Task C',
    'start_date' => $now->copy()->addDays(8)->toDateString(),
    'end_date' => $now->copy()->addDays(14)->toDateString(),
    'progress' => 100,
]);

echo json_encode([$a->toArray(), $b->toArray(), $c->toArray()]);
