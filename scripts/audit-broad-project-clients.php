<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'silverlamb759_sunbwork') {
    throw new RuntimeException('STOP: database configuration differs');
}
$db = Illuminate\Support\Facades\DB::connection('mysql');
$companyId = 2;
$departmentId = 1;
// All business queries below run inside a read-only transaction on the default app DB.
if ($db->transactionLevel() !== 0) throw new RuntimeException('STOP: unexpected active transaction');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    if (! $db->table('departments')->where('id', $departmentId)->where('company_id', $companyId)->where('code', 'INFO')->exists()
        || ! $db->table('companies')->where('id', $companyId)->where('company_type', '!=', 'general')->exists()) {
        throw new RuntimeException('STOP: previously confirmed INFO company/department context differs');
    }
    $groups = $db->table('broad_projects')->where('company_id', $companyId)->where('department_id', $departmentId)
        ->orderByRaw('sort_order IS NULL')->orderBy('sort_order')->orderBy('id')
        ->get(['id','name','parent_id','client_id','fiscal_year','is_active','sort_order']);
    $ids = $groups->pluck('id')->all();
    $projectCompany = fn ($q) => $q->where('p.company_id', $companyId)->orWhereNull('p.company_id');
    $columns = ['p.id','p.title','p.client_id','p.broad_project_id','p.completed'];
    $linked = $db->table('project_jobs as p')->whereIn('p.broad_project_id', $ids)->where($projectCompany)
        ->orderBy('p.id')->get($columns);
    $unlinkedQuery = $db->table('project_jobs as p')->whereNull('p.broad_project_id')->where($projectCompany)
        ->where(function ($q) use ($companyId, $departmentId) {
            $q->whereExists(function ($q) use ($companyId, $departmentId) {
                $q->selectRaw('1')->from('users as owner')->whereColumn('owner.id','p.user_id')
                    ->where('owner.company_id',$companyId)->where('owner.department_id',$departmentId);
            })->orWhereExists(function ($q) use ($companyId, $departmentId) {
                $q->selectRaw('1')->from('project_job_assignments as a')->join('users as worker','worker.id','=','a.user_id')
                    ->whereColumn('a.project_job_id','p.id')->where('worker.company_id',$companyId)->where('worker.department_id',$departmentId);
            });
        });
    $unlinkedCount = (clone $unlinkedQuery)->count();
    if ($unlinkedCount > 10000 || $linked->count() > 10000) {
        throw new RuntimeException('STOP: project count exceeds 10000; prepare explicit paged read rather than truncate');
    }
    $unlinked = $unlinkedQuery->orderBy('p.id')->get($columns);
    $clientIds = $linked->pluck('client_id')->merge($unlinked->pluck('client_id'))->merge($groups->pluck('client_id'))->filter()->unique()->values();
    $clients = $db->table('clients')->whereIn('id',$clientIds)->where(fn ($q) => $q->where('company_id',$companyId)->orWhereNull('company_id'))
        ->orderBy('id')->get(['id','name','company_id'])->keyBy('id');
    $rows = [];
    foreach ($groups as $group) {
        $children = $groups->where('parent_id',$group->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $members = array_merge([(int) $group->id], $children);
        $direct = $linked->where('broad_project_id',$group->id);
        $all = $linked->filter(fn ($project) => in_array((int) $project->broad_project_id,$members,true));
        $candidates = []; $missing = 0;
        foreach ($all->groupBy('client_id') as $clientId => $projects) {
            $client = $clients->get($clientId);
            if (! $clientId || ! $client) { $missing += $projects->count(); continue; }
            $candidates[] = ['id'=>(int)$client->id,'name'=>$client->name,'project_count'=>$projects->count(),'is_placeholder'=>trim($client->name) === 'その他'];
        }
        $candidateCount = count($candidates);
        $category = $candidateCount === 0 ? 'unresolved' : ($candidateCount > 1 ? 'multiple' : ($missing ? 'single_known_with_unknown' : ($candidates[0]['is_placeholder'] ? 'placeholder_only' : 'single')));
        $rows[] = ['id'=>(int)$group->id,'name'=>$group->name,'parent_id'=>$group->parent_id,'fiscal_year'=>$group->fiscal_year,
            'is_active'=>(bool)$group->is_active,'sort_order'=>$group->sort_order,'current_client_id'=>$group->client_id,
            'current_client_name'=>$clients->get($group->client_id)?->name,'child_ids'=>$children,
            'direct_project_count'=>$direct->count(),'including_children_project_count'=>$all->count(),
            'missing_or_out_of_scope_client_project_count'=>$missing,'category'=>$category,'candidates'=>$candidates];
    }
    $report = ['meta'=>['generated_at'=>now('Asia/Tokyo')->toIso8601String(),'revision'=>getenv('SB_CLIENT_AUDIT_REV'),
        'company_id'=>$companyId,'department_id'=>$departmentId,'department_code'=>'INFO','read_only'=>true,
        'scope'=>'All existing linked classifications; unlinked projects with INFO owner or INFO assignee. Completed projects included. No title-based matching or registration.',
        'groups'=>$groups->count(),'linked_projects'=>$linked->count(),'unlinked_projects'=>$unlinkedCount],
        'groups'=>$rows,'clients'=>$clients->values()->all(),'linked_projects'=>$linked->all(),'unlinked_projects'=>$unlinked->all()];
    echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
}
