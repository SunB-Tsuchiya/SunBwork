<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProjectJobAssignment;

$assignment = ProjectJobAssignment::with(['statusModel', 'jobAssignmentMessages', 'jobAssignmentMessages.sender', 'projectJob', 'projectJob.client'])->find(8);
if (!$assignment) {
    echo "null\n";
    exit(0);
}

echo json_encode($assignment->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
