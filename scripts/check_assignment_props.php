<?php
// scripts/check_assignment_props.php
// Boot Laravel and dump companies/types/sizes/stages/statuses as seen by ProjectJobAssignmentsController::create
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ProjectJob;
use App\Models\Company;
use App\Models\WorkItemType;
use App\Models\Size;
use App\Models\Stage;
use App\Models\Status;

// find a superadmin user
$super = User::where('user_role', 'superadmin')->first();
if (! $super) {
    echo "No superadmin user found.\n";
    exit(1);
}
Auth::login($super);

$pj = ProjectJob::first();
if (! $pj) {
    echo "No ProjectJob found.\n";
    exit(1);
}

// replicate controller logic for companies
// request()->user() may be unavailable in CLI bootstrap; use the located superadmin user
$user = $super;
$userRole = $user->user_role ?? null;
$userCompanyId = $user->company_id ?? null;
$userDepartmentId = $user->department_id ?? null;

$companies = collect();
if ($userRole === 'superadmin') {
    $companies = Company::with(['departments' => function ($q) {
        $q->orderBy('sort_order');
    }])->orderBy('name')->get();
} elseif ($userRole === 'admin') {
    if ($userCompanyId) {
        $companies = Company::where('id', $userCompanyId)->with(['departments' => function ($q) {
            $q->orderBy('sort_order');
        }])->get();
    }
} else {
    $companies = collect();
}

$types = WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
$sizes = Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
$stages = Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
$statuses = Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);

$out = [
    'user' => ['id' => $user->id, 'role' => $userRole, 'company_id' => $userCompanyId, 'department_id' => $userDepartmentId],
    'project_job_id' => $pj->id,
    'companies_count' => $companies->count(),
    'companies_sample' => $companies->map(function ($c) {
        // ensure departments are converted from Collection/Models to simple arrays of id/name
        $depts = [];
        if (isset($c->departments) && is_iterable($c->departments)) {
            // if it's a Collection of models
            foreach ($c->departments as $d) {
                $depts[] = ['id' => $d->id ?? null, 'name' => $d->name ?? null];
            }
        }
        return ['id' => $c->id, 'name' => $c->name, 'departments' => $depts];
    })->take(5)->values(),
    'types_count' => $types->count(),
    'types_sample' => $types->take(10)->map(function ($t) {
        return ['id' => $t->id, 'name' => $t->name, 'company_id' => $t->company_id, 'department_id' => $t->department_id];
    }),
    'sizes_count' => $sizes->count(),
    'sizes_sample' => $sizes->take(10)->map(function ($s) {
        return ['id' => $s->id, 'name' => $s->name, 'company_id' => $s->company_id, 'department_id' => $s->department_id];
    }),
    'stages_count' => $stages->count(),
    'statuses_count' => $statuses->count(),
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
