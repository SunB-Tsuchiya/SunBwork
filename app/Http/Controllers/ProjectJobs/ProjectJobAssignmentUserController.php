<?php

namespace App\Http\Controllers\ProjectJobs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectJobAssignmentUserController extends Controller
{
    public function create(Request $request)
    {
        $jobId = $request->query('job');
        $user = $request->user();

        // JobBox/Show から「マイジョブとして登録」で来た場合（progress_cell なし）
        // source_job_assignment_id に Coordinator 割当の ID が渡される
        $sourceJobAssignmentId = $request->query('source_job_assignment_id');

        // If no prefill params, keep existing behaviour and redirect to the job create flow
        if (!$jobId && !$sourceJobAssignmentId && !$request->query('sender_id') && !$request->query('desired_end_date') && !$request->query('desired_time') && !$request->query('project_job_id')) {
            return redirect()->route('events.create_job');
        }

        // source_job_assignment_id が渡された場合は Coordinator assignment から prefill 情報を取得
        $sourceAssignment = null;
        if ($sourceJobAssignmentId && !$jobId) {
            try {
                $sourceAssignment = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'size', 'stage', 'workItemType'])->find($sourceJobAssignmentId);
            } catch (\Throwable $__e) {
                $sourceAssignment = null;
            }
        }

        // Build userProjects / userClients similar to EventController::createJob
        $userClients = [];
        $userProjects = [];
        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobs = $ptms->map(function ($ptm) {
                return $ptm->projectJob;
            })->filter();

            $userProjects = $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title ?? ($job->name ?? null),
                    'client_id' => $job->client ? $job->client->id : null,
                ];
            })->values();

            $clients = $jobs->map(function ($job) {
                return $job->client;
            })->filter()->unique('id')->values();

            $userClients = $clients->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? null)];
            })->values();
        } catch (\Throwable $__e) {
            $userClients = [];
            $userProjects = [];
        }

        // minimal members list (current user)
        $members = [];
        try {
            if ($user) $members = [['id' => $user->id, 'name' => $user->name]];
        } catch (\Throwable $__e) {
            $members = [];
        }

        $company = null;
        $department = null;
        try {
            if ($user && isset($user->company_id) && $user->company_id) {
                $company = \App\Models\Company::find($user->company_id);
            }
            if ($user && isset($user->department_id) && $user->department_id) {
                $department = \App\Models\Department::find($user->department_id);
            }
        } catch (\Throwable $__e) {
            $company = null;
            $department = null;
        }

        // lookup lists
        $types = [];
        $sizes = [];
        $stages = [];
        $statuses = [];
        $difficulties = [];
        try {
            $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
            $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
            $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
            $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);
        } catch (\Throwable $__e) {
            $types = [];
            $sizes = [];
            $stages = [];
            $statuses = [];
            $difficulties = [];
        }

        // Build a single prefill assignment object for the form using query params and optional job source
        if ($sourceAssignment) {
            // Coordinator 割当から prefill を構築（JobBox/Show からの「マイジョブとして登録」）
            // 複合ジョブの場合は file_info からページ数・ファイル数を amounts に自動設定
            $sourceAmounts = null;
            $sourceAmountsUnit = 'page';
            $estimatedSizeId = null;

            if (!empty($sourceAssignment->file_info)) {
                $fi = is_array($sourceAssignment->file_info) ? $sourceAssignment->file_info : json_decode($sourceAssignment->file_info, true);
                if ($fi) {
                    // ページ数・ファイル数を amounts に自動設定
                    $totalPages = $fi['total_pages'] ?? 0;
                    $totalFiles = $fi['total_files'] ?? 0;
                    if ($totalPages > 0) {
                        $sourceAmounts = $totalPages;
                        $sourceAmountsUnit = 'page';
                    } elseif ($totalFiles > 0) {
                        $sourceAmounts = $totalFiles;
                        $sourceAmountsUnit = 'file';
                    }

                    // ページ型ファイル（PDF・Word・AI・EPS）のサイズ推定
                    // 全ファイルが同一 doc_size の場合のみ Size レコードにマッチング
                    if (empty($sourceAssignment->size_id) && !empty($fi['files'])) {
                        $pageExts = ['pdf', 'ai', 'docx', 'doc', 'eps'];
                        $pageFiles = array_filter($fi['files'], fn($f) =>
                            in_array(strtolower($f['ext'] ?? ''), $pageExts)
                        );
                        $allFiles = $fi['files'];

                        // 「PDFやWordのみ」= 全ファイルがページ型、または全ページ型ファイルが同一サイズ
                        $onlyPageType = !empty($pageFiles) && count($pageFiles) === count($allFiles);
                        if (!empty($pageFiles)) {
                            $docSizes = array_unique(array_filter(
                                array_map(fn($f) => $f['doc_size'] ?? null, $pageFiles)
                            ));
                            // 全ページ型ファイルが同一サイズ（かつPDFのみ or サイズ差異なし）
                            if (count($docSizes) === 1 && ($onlyPageType || true)) {
                                $docSize = reset($docSizes);
                                // "B5(JIS)" → "B5"、"A4" → "A4" のように括弧内を除去してマッチング
                                $baseName = preg_replace('/\s*\([^)]*\)\s*$/', '', $docSize);
                                try {
                                    $sizeRecord = \App\Models\Size::where('name', $baseName)
                                        ->orWhere('name', $docSize)
                                        ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$baseName])
                                        ->first();
                                    if ($sizeRecord) $estimatedSizeId = $sizeRecord->id;
                                } catch (\Throwable $__e) {}
                            }
                        }
                    }
                }
            }

            // amounts フォールバック: file_info → assignment.amounts → projectJob.page_count
            if ($sourceAmounts === null && $sourceAssignment->amounts) {
                $sourceAmounts     = (int) $sourceAssignment->amounts;
                $sourceAmountsUnit = $sourceAssignment->amounts_unit ?? 'page';
            }
            if ($sourceAmounts === null && $sourceAssignment->projectJob?->page_count) {
                $sourceAmounts     = (int) $sourceAssignment->projectJob->page_count;
                $sourceAmountsUnit = 'page';
            }

            $prefill = [
                'project_job_id' => $sourceAssignment->project_job_id,
                '_client_id' => $sourceAssignment->projectJob?->client?->id ?? ($request->query('_client_id') ?: ''),
                'title_suffix' => $sourceAssignment->title ?? $request->query('title') ?? '',
                'detail' => $sourceAssignment->detail ?? '',
                'work_item_type_id' => $sourceAssignment->work_item_type_id ?? null,
                'size_id' => $sourceAssignment->size_id ?? $estimatedSizeId,
                'stage_id' => $sourceAssignment->stage_id ?? null,
                'difficulty_id' => $sourceAssignment->difficulty_id ?? null,
                'desired_end_date' => $sourceAssignment->desired_end_date
                    ? (is_string($sourceAssignment->desired_end_date) ? $sourceAssignment->desired_end_date : $sourceAssignment->desired_end_date->format('Y-m-d'))
                    : ($request->query('desired_end_date') ?: null),
                'desired_time' => $sourceAssignment->desired_time ?? null,
                'estimated_hours' => $sourceAssignment->estimated_hours ?? ($request->query('estimated_hours') ?: null),
                'sender_id' => $user ? $user->id : null,
                'amounts' => $sourceAmounts,
                'amounts_unit' => $sourceAmountsUnit,
                'status_id' => null,
                // ファイル情報を引き継ぐ（表示・保存用）
                'file_info' => $sourceAssignment->file_info ?? null,
                // 依頼ジョブをマイジョブで置き換えたことを記録（coordinator jobbox の非表示フィルタに使用）
                'supersedes_assignment_id' => (int) $sourceJobAssignmentId,
            ];
        } else {
            $prefill = [
                'project_job_id' => $request->query('project_job_id') ?: ($request->query('projectJob') ?: null),
                '_client_id' => $request->query('_client_id') ?: '',
                'title_suffix' => $request->query('title') ?? '',
                'work_item_type_id' => $request->query('work_item_type_id') ?: null,
                'size_id' => $request->query('size_id') ?: null,
                'stage_id' => $request->query('stage_id') ?: null,
                'difficulty_id' => $request->query('difficulty_id') ?: null,
                'sender_id' => $request->query('sender_id') ?: ($user ? $user->id : null),
                'desired_end_date' => $request->query('desired_end_date') ?: null,
                'desired_time' => $request->query('desired_time') ?: null,
                'estimated_hours' => $request->query('estimated_hours') ?: null,
            ];
        }

        $props = [
            'projectJob' => $sourceAssignment?->projectJob ?? null,
            'userClients' => $userClients,
            'userProjects' => $userProjects,
            'members' => $members,
            'company' => $company,
            'department' => $department,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            // supply assignments array so AssignmentForm_user will prefill
            'assignments' => [$prefill],
            // 複合ジョブ由来の場合、ファイル情報を別途渡してUIに表示
            'source_job_type' => $sourceAssignment?->job_type ?? null,
            'source_file_info' => $sourceAssignment?->file_info ?? null,
        ];

        // source_job_assignment_id がある場合（JobBox/Show からの独立ジョブ作成）→ MyJobBox/Create_user
        // それ以外（従来の coordinator 割当経由）→ JobBox/create_user
        $view = $sourceJobAssignmentId ? 'MyJobBox/Create_user' : 'JobBox/create_user';

        return Inertia::render($view, $props);
    }

    public function edit(Request $request)
    {
        $user = $request->user();
        $jobId = $request->query('job');
        if (!$jobId) {
            return redirect()->route('events.create');
        }

        // load lookup lists
        try {
            $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
            $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
            $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
            $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        } catch (\Throwable $__e) {
            $types = [];
            $sizes = [];
            $stages = [];
            $statuses = [];
        }

        // Attempt to find the existing ProjectJobAssignment to use as prefill
        $assignment = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'user', 'size', 'stage', 'workItemType', 'statusModel'])->find($jobId);
        if (!$assignment) {
            // fallback to events.create when not found
            return redirect()->route('events.create', ['job' => $request->query('job')]);
        }

        $prefill = $assignment->toEventPrefill();

        $props = [
            'projectJob' => $assignment->projectJob ?? null,
            'userClients' => [],
            'userProjects' => [],
            'members' => auth()->user() ? [] : [],
            'company' => null,
            'department' => null,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            // supply assignments array so AssignmentForm_user will prefill
            'assignments' => [$prefill],
        ];

        return Inertia::render('MyJobBox/Edit_user', $props);
    }
}
