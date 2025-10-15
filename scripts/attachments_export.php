<?php

/**
 * Legacy attachments export
 *
 * WARNING: This script exports legacy columns (message_id, diary_id, event_id).
 * After migrating to `attachmentables` pivot table prefer using
 * `scripts/attachments_export_with_pivots.php` which exports both attachments
 * and attachmentables in separate CSVs. Keep this script for backward
 * compatibility only.
 */
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = \DB::table('attachments')->select('id', 'path', 'original_name', 'mime_type', 'size', 'user_id', 'message_id', 'diary_id', 'event_id', 'owner_type', 'owner_id', 'created_at')->get();
$f = fopen(__DIR__ . '/../attachments_export.csv', 'w');
fputcsv($f, ['id', 'path', 'original_name', 'mime_type', 'size', 'user_id', 'message_id', 'diary_id', 'event_id', 'owner_type', 'owner_id', 'created_at']);
foreach ($rows as $r) {
    fputcsv($f, [$r->id, $r->path, $r->original_name, $r->mime_type, $r->size, $r->user_id, $r->message_id, $r->diary_id, $r->event_id, $r->owner_type, $r->owner_id, $r->created_at]);
}
fclose($f);
echo "attachments_export.csv written\n";
