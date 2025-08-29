<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\ProjectSchedule;

$exists = ProjectSchedule::where('project_job_id', 4)->exists();
echo $exists ? "true\n" : "false\n";
