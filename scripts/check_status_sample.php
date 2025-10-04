<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\JobAssignmentMessage;

$jam = JobAssignmentMessage::with(['projectJobAssignment.statusModel', 'projectJobAssignment.user'])->first();
if (!$jam) {
    echo json_encode(null);
    exit(0);
}
// convert to array and print
echo json_encode($jam->toArray());
