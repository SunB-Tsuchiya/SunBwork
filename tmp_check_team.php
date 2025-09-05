<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$team = App\Models\Team::with(['company', 'department'])->find(4);
if (!$team) {
    echo "Team not found\n";
    exit(1);
}
$companyId = $team->company_id;
$users = [];
if ($companyId) {
    $users = App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
        ->where('company_id', $companyId)
        ->get()
        ->toArray();
}
$output = [
    'team' => $team->toArray(),
    'users_count' => count($users),
    'users_sample' => array_slice($users, 0, 5),
];
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
