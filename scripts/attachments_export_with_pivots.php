<?php
// Exports attachments and attachmentables into CSV files under /exports
// Safe: does not modify DB, only reads and writes CSVs

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$exportDir = __DIR__ . '/../exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0775, true);
}

// Export attachments (exclude legacy-only ad-hoc columns if desired)
$attachments = DB::table('attachments')
    ->select('id', 'path', 'original_name', 'mime_type', 'size', 'user_id', 'owner_type', 'owner_id', 'status', 'created_at', 'updated_at')
    ->orderBy('id')
    ->get();

$attachmentables = DB::table('attachmentables')
    ->select('id', 'attachment_id', 'attachable_type', 'attachable_id', 'created_at', 'updated_at')
    ->orderBy('id')
    ->get();

$attFile = $exportDir . '/attachments.csv';
$attablesFile = $exportDir . '/attachmentables.csv';

$f1 = fopen($attFile, 'w');
fputcsv($f1, ['id', 'path', 'original_name', 'mime_type', 'size', 'user_id', 'owner_type', 'owner_id', 'status', 'created_at', 'updated_at']);
foreach ($attachments as $r) {
    fputcsv($f1, [$r->id, $r->path, $r->original_name, $r->mime_type, $r->size, $r->user_id, $r->owner_type, $r->owner_id, $r->status, $r->created_at, $r->updated_at]);
}
fclose($f1);

$f2 = fopen($attablesFile, 'w');
fputcsv($f2, ['id', 'attachment_id', 'attachable_type', 'attachable_id', 'created_at', 'updated_at']);
foreach ($attachmentables as $r) {
    fputcsv($f2, [$r->id, $r->attachment_id, $r->attachable_type, $r->attachable_id, $r->created_at, $r->updated_at]);
}
fclose($f2);

echo "Wrote: " . $attFile . " and " . $attablesFile . "\n";
