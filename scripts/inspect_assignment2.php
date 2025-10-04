<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProjectJobAssignment;
use App\Models\JobAssignmentMessage;

$id = 8;
$assignment = ProjectJobAssignment::with(['statusModel', 'projectJob.client', 'user', 'size', 'stage', 'workItemType'])->find($id);
$messages = JobAssignmentMessage::with(['sender', 'message', 'projectJobAssignment.statusModel'])->where('project_job_assignment_id', $id)->get();

$out = [
    'assignment' => $assignment ? $assignment->toArray() : null,
    'messages' => $messages->toArray(),
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
