<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\ProjectJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MGinbonProjectLinkController extends Controller
{
    public function create(Request $request, MGinbonProject $project): RedirectResponse
    {
        abort_if($project->project_job_id, 422, 'すでにSBWork案件へ接続されています。');
        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id') : $request->user()?->company_id;
        abort_unless($companyId, 422, '会社を選択してください。');

        $projectJob = DB::transaction(function () use ($request, $project, $companyId) {
            $job = ProjectJob::create([
                'title' => "{$project->year}年 銀本制作",
                'detail' => 'MGinbon（中学入試問題集）専用のJobBox・カレンダー連携案件',
                'user_id' => $request->user()?->id,
                'company_id' => $companyId,
                'completed' => false,
            ]);
            DB::connection('mginbon')->transaction(function () use ($request, $project, $job) {
                $project->update(['project_job_id' => $job->id]);
                DB::connection('mginbon')->table('mginbon_change_logs')->insert([
                    'mginbon_project_id' => $project->id, 'changed_by' => $request->user()?->id,
                    'field_path' => 'project.project_job_id', 'old_value' => json_encode(null),
                    'new_value' => json_encode($job->id), 'source' => 'manual',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
            return $job;
        });

        return back()->with('success', "{$projectJob->title}を作成して接続しました。");
    }

    public function update(Request $request, MGinbonProject $project): RedirectResponse
    {
        $validated = $request->validate(['project_job_id' => ['nullable', 'integer', 'exists:project_jobs,id']]);
        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id') : $request->user()?->company_id;
        abort_if($request->user()?->user_role === 'superadmin' && $companyId === null, 422, '会社を選択してください。');

        $projectJob = isset($validated['project_job_id'])
            ? ProjectJob::query()->where('company_id', $companyId)->findOrFail($validated['project_job_id'])
            : null;
        $old = $project->project_job_id;

        DB::connection('mginbon')->transaction(function () use ($request, $project, $projectJob, $old) {
            $project->update(['project_job_id' => $projectJob?->id]);
            DB::connection('mginbon')->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $project->id, 'changed_by' => $request->user()?->id,
                'field_path' => 'project.project_job_id', 'old_value' => json_encode($old),
                'new_value' => json_encode($projectJob?->id), 'source' => 'manual',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', $projectJob ? 'SBWork案件へ接続しました。' : 'SBWork案件との接続を解除しました。');
    }
}
