<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userId = 31;
$rows = DB::select('select distinct project_job_assignment_id as pid from events where user_id = ? and project_job_assignment_id is not null', [$userId]);
$arr = [];
foreach ($rows as $r) {
    $arr[] = $r->pid;
}
echo json_encode(['assignmentIds' => $arr], JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (count($arr) > 0) {
    $in = implode(',', array_map('intval', $arr));
} else {
    $in = '0';
}
$progress = DB::select("select assignment_id from progress_cells where assignment_id in ($in)");
$parr = [];
foreach ($progress as $p) {
    $parr[] = $p->assignment_id;
}
echo json_encode(['progressAssignmentIds' => $parr], JSON_UNESCAPED_UNICODE) . PHP_EOL;

$meta = DB::select("select id, source_assignment_id, supersedes_assignment_id from project_job_assignments where id in ($in)");
// normalize meta to associative array keyed by id
$metaAssoc = [];
foreach ($meta as $m) {
    $metaAssoc[$m->id] = ['source_assignment_id' => $m->source_assignment_id, 'supersedes_assignment_id' => $m->supersedes_assignment_id];
}
echo json_encode(['metaRows' => $metaAssoc], JSON_UNESCAPED_UNICODE) . PHP_EOL;

// compute hasProgress per assignment
foreach ($arr as $aid) {
    $hasProgress = in_array((int)$aid, $parr, true);
    if (!$hasProgress && isset($metaAssoc[$aid])) {
        $hasProgress = !empty($metaAssoc[$aid]['source_assignment_id']) || !empty($metaAssoc[$aid]['supersedes_assignment_id']);
    }
    echo "aid=$aid hasProgress=" . ($hasProgress ? 'true' : 'false') . PHP_EOL;
}
