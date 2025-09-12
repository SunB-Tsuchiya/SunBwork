<?php
// Quick smoke test: create a WorkItem and, if a ProjectJob exists, create a linked ProjectJobAssignment
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WorkItem;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;

try {
    $pj = ProjectJob::first();
    if (!$pj) {
        // create a minimal project job if none exists
        $pj = ProjectJob::create(['name' => 'smoke-test-project', 'company_id' => null]);
        echo "Created ProjectJob id={$pj->id}\n";
    } else {
        echo "Using ProjectJob id={$pj->id}\n";
    }

    $wi = WorkItem::create([
        'title' => 'smoke test item ' . time(),
        'description' => 'created by smoke test',
        'created_by' => null
    ]);
    echo "Created WorkItem id={$wi->id}\n";

    $assignment = ProjectJobAssignment::create([
        'project_job_id' => $pj->id,
        'title' => 'assignment for workitem ' . $wi->id,
        'detail' => 'auto-linked',
        'work_item_id' => $wi->id,
    ]);

    echo "Created ProjectJobAssignment id={$assignment->id} linked to work_item_id={$assignment->work_item_id}\n";
    echo "OK\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
