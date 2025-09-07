<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Diary;

$userId = 9;
$date = '2025-09-30';
$ids = Diary::where('date', $date)
    ->whereRaw('JSON_CONTAINS(read_by, JSON_ARRAY(?)) = 0', [$userId])
    ->pluck('id')
    ->toArray();
echo json_encode($ids) . PHP_EOL;
$all = Diary::where('date', $date)->pluck('id')->toArray();
echo "all: " . json_encode($all) . PHP_EOL;
$read1848 = Diary::find(1848)?->read_by ?? null;
echo "1848 read_by: " . json_encode($read1848) . PHP_EOL;
return 0;
