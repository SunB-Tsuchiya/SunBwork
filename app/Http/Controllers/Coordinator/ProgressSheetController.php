<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressSheet;
use App\Models\ProgressTemplate;
use App\Models\ProgressRow;
use App\Models\ProgressCell;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProgressSheetController extends Controller
{
    /**
     * 案件配下に新規シートを作成
     */
    public function store(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJobAccess($request->user(), $projectJob);

        $validated = $request->validate([
            'name'                      => 'required|string|max:255',
            'template_id'               => 'nullable|exists:progress_templates,id',
            'column_config'             => 'nullable|array',
            'initial_rows'              => 'nullable|array',
            'initial_rows.*.label'      => 'required|string|max:255',
            'initial_rows.*.start_date' => 'nullable|date',
            'initial_rows.*.end_date'   => 'nullable|date',
        ]);

        $columnConfig = $validated['column_config'] ?? [];

        // テンプレートがある場合はそのcolumn_configをコピー
        if (!empty($validated['template_id'])) {
            $template = ProgressTemplate::find($validated['template_id']);
            if ($template) {
                $columnConfig = $columnConfig ?: $template->column_config;
            }
        }

        // column_configが空なら空配列をデフォルト
        if (empty($columnConfig)) {
            $columnConfig = [];
        }

        $sheet = ProgressSheet::create([
            'project_job_id' => $projectJob->id,
            'template_id'    => $validated['template_id'] ?? null,
            'name'           => $validated['name'],
            'column_config'  => $columnConfig,
            'created_by'     => $request->user()->id,
            'sort_order'     => ProgressSheet::where('project_job_id', $projectJob->id)->max('sort_order') + 1,
        ]);

        // カレンダーから作成の場合：initial_rows で行と日付セルを一括作成
        if (!empty($validated['initial_rows'])) {
            $order = 0;
            foreach ($validated['initial_rows'] as $rowData) {
                $row = ProgressRow::create([
                    'sheet_id' => $sheet->id,
                    'label'    => $rowData['label'],
                    'order'    => $order++,
                    'deadline' => $rowData['end_date'] ?? null,
                ]);
                if (!empty($rowData['start_date'])) {
                    ProgressCell::create([
                        'row_id'     => $row->id,
                        'col_key'    => 'start_date',
                        'cell_type'  => 'date',
                        'value_date' => $rowData['start_date'],
                    ]);
                }
                if (!empty($rowData['end_date'])) {
                    ProgressCell::create([
                        'row_id'     => $row->id,
                        'col_key'    => 'end_date',
                        'cell_type'  => 'date',
                        'value_date' => $rowData['end_date'],
                    ]);
                }
            }
        }

        // テンプレートのrow_configがあれば台割行を初期作成
        if (!empty($template) && !empty($template->row_config)) {
            $order = 0;
            foreach ($template->row_config as $rowDef) {
                if (!empty($rowDef['children'])) {
                    // グループ親行
                    $parent = ProgressRow::create([
                        'sheet_id'   => $sheet->id,
                        'label'      => $rowDef['label'] ?? '',
                        'order'      => $order++,
                        'parent_id'  => null,
                    ]);
                    foreach ($rowDef['children'] as $childDef) {
                        ProgressRow::create([
                            'sheet_id'   => $sheet->id,
                            'label'      => $childDef['label'] ?? '',
                            'order'      => $order++,
                            'parent_id'  => $parent->id,
                        ]);
                    }
                } else {
                    ProgressRow::create([
                        'sheet_id'   => $sheet->id,
                        'label'      => $rowDef['label'] ?? '',
                        'order'      => $order++,
                        'parent_id'  => null,
                    ]);
                }
            }
        }

        return redirect()->route('coordinator.progress_sheets.show', $sheet->id);
    }

    /**
     * 案件配下のシートの並び順を更新
     * body: { ids: [1, 3, 2, ...] } (並び順通りのシートID配列)
     */
    public function reorderSheets(Request $request, ProjectJob $projectJob): \Illuminate\Http\JsonResponse
    {
        $this->authorizeJobAccess($request->user(), $projectJob);

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($projectJob, $validated) {
            foreach ($validated['ids'] as $order => $id) {
                ProgressSheet::where('id', $id)
                    ->where('project_job_id', $projectJob->id)
                    ->update(['sort_order' => $order]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * シート詳細（進行管理表）
     */
    public function show(Request $request, ProgressSheet $sheet)
    {
        $sheet->load(['projectJob.client', 'projectJob.size', 'projectJob.user', 'projectJob.coordinators']);
        $projectJob = $sheet->projectJob;

        $canEdit = $this->canEdit($request->user(), $projectJob, $sheet);

        $rows = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order', 'parent_id']);

        $rawCells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,title,detail,desired_end_date,completed,proof_completed_at,user_id,sender_id,subcontractor_id',
                'assignment.user:id,name',
                'proofAssignment:id,title,completed,proof_completed_at,user_id,sender_id',
                'schedule:id,name,end_date,completed_at',
                'noteUser:id,name,user_role',
            ])
            ->get();

        // proof_request pending 状態（依頼済みで未受理）を一括取得
        $pendingProofRequestMap = \App\Models\ProofRequest::where('status', 'pending')
            ->whereIn('proof_cell_id', $rawCells->pluck('id')->filter())
            ->get(['id', 'proof_cell_id', 'deadline'])
            ->keyBy('proof_cell_id');

        $cells = $rawCells->map(fn($c) => [
                'id'                          => $c->id,
                'row_id'                      => $c->row_id,
                'col_key'                     => $c->col_key,
                'cell_type'                   => $c->cell_type,
                'value_text'                  => $c->value_text,
                'value_date'                  => $c->value_date?->format('Y-m-d'),
                'value_bool'                  => $c->value_bool,
                'value_user_id'               => $c->value_user_id ?? $c->assignment?->user_id,
                'value_user_name'             => $c->valueUser?->name ?? $c->assignment?->user?->name,
                'value_subcontractor_id'      => $c->value_subcontractor_id,
                'value_subcontractor_name'    => $c->valueSubcontractor?->name,
                'assignment_id'               => $c->assignment_id,
                'assignment_title'            => $c->assignment?->title,
                'assignment_completed'        => $c->assignment?->completed,
                'assignment_proof_completed'  => $c->assignment?->proof_completed_at !== null,
                'assignment_user_id'          => $c->assignment?->user_id,
                'assignment_subcontractor_id' => $c->assignment?->subcontractor_id,
                'assignment_end_date'         => $c->assignment?->desired_end_date?->format('Y-m-d'),
                'proof_assignment_id'         => $c->proof_assignment_id,
                'proof_assignment_title'      => $c->proofAssignment?->title,
                'proof_assignment_completed'  => $c->proofAssignment?->completed || $c->proofAssignment?->proof_completed_at !== null,
                'proof_request_pending'       => $c->id ? $pendingProofRequestMap->has($c->id) : false,
                'proof_request_id'            => $c->id ? ($pendingProofRequestMap->get($c->id)?->id ?? null) : null,
                'proof_request_deadline'      => $c->id ? ($pendingProofRequestMap->get($c->id)?->deadline?->format('Y-m-d') ?? null) : null,
                // V2フィールド
                'schedule_id'                 => $c->schedule_id,
                'schedule_name'               => $c->schedule?->name,
                'schedule_end_date'           => $c->schedule?->end_date?->format('Y-m-d'),
                'schedule_completed_at'       => $c->schedule?->completed_at?->format('Y-m-d H:i:s'),
                'cell_deadline'               => $c->cell_deadline?->format('Y-m-d'),
                'cell_note'                   => $c->cell_note,
                'cell_note_user_name'          => $c->noteUser?->name,
                'cell_note_user_role'          => $c->noteUser?->user_role,
                'completed_at'                => $c->completed_at?->format('Y-m-d H:i:s'),
                'work_minutes'                => null, // events から後で上書き
                'proof_work_minutes'          => 0,    // events から後で上書き
            ]);

        // events から worker セルの作業時間をバッチ算出（1イベント最大1440分でキャップ）
        $workerAssignmentIds = $cells->whereNotNull('assignment_id')->pluck('assignment_id')->unique()->values()->toArray();

        // coordinator PJA を supersede している worker 自己割当 PJA も取得
        $supersedingPjas = [];
        if (!empty($workerAssignmentIds)) {
            $supRows = DB::table('project_job_assignments')
                ->whereIn('supersedes_assignment_id', $workerAssignmentIds)
                ->select('id', 'supersedes_assignment_id')
                ->get();
            foreach ($supRows as $r) {
                $supersedingPjas[(int)$r->supersedes_assignment_id][] = (int)$r->id;
            }
        }
        $supersedingIds = array_merge(...array_values($supersedingPjas) ?: [[]]);
        $allPjaIds = array_unique(array_merge($workerAssignmentIds, $supersedingIds));

        $evtMinutes = [];
        if (!empty($allPjaIds)) {
            $rawMinutes = DB::table('events')
                ->whereIn('project_job_assignment_id', $allPjaIds)
                ->whereNotNull('ends_at')
                ->selectRaw('project_job_assignment_id,
                    COALESCE(SUM(GREATEST(0, LEAST(TIMESTAMPDIFF(MINUTE, starts_at, ends_at), 1440)
                        - COALESCE(interruption_minutes, 0))), 0) as total')
                ->groupBy('project_job_assignment_id')
                ->pluck('total', 'project_job_assignment_id')
                ->toArray();
            foreach ($workerAssignmentIds as $coordId) {
                $total = (int)($rawMinutes[$coordId] ?? 0);
                foreach ($supersedingPjas[$coordId] ?? [] as $wId) {
                    $total += (int)($rawMinutes[$wId] ?? 0);
                }
                $evtMinutes[$coordId] = $total;
            }
        }

        // proof_assignment_id 用のイベントも取得
        $proofAssignmentIds = $cells->whereNotNull('proof_assignment_id')->pluck('proof_assignment_id')->unique()->values()->toArray();
        $supersedingProofPjas = [];
        if (!empty($proofAssignmentIds)) {
            // supersedes_assignment_id または coordinator_assignment_id で紐づく pja101 を取得
            $supProofRows = DB::table('project_job_assignments')
                ->where(function ($q) use ($proofAssignmentIds) {
                    $q->whereIn('supersedes_assignment_id', $proofAssignmentIds)
                      ->orWhereIn('coordinator_assignment_id', $proofAssignmentIds);
                })
                ->whereColumn('sender_id', 'user_id')
                ->select('id', 'supersedes_assignment_id', 'coordinator_assignment_id')
                ->get();
            foreach ($supProofRows as $r) {
                $parentId = $r->coordinator_assignment_id ?? $r->supersedes_assignment_id;
                if ($parentId) {
                    $supersedingProofPjas[(int)$parentId][] = (int)$r->id;
                }
            }
        }
        $supersedingProofIds = array_merge(...array_values($supersedingProofPjas) ?: [[]]);
        $allProofPjaIds = array_unique(array_merge($proofAssignmentIds, $supersedingProofIds));

        $proofEvtMinutes = [];
        if (!empty($allProofPjaIds)) {
            $rawProofMinutes = DB::table('events')
                ->whereIn('project_job_assignment_id', $allProofPjaIds)
                ->whereNotNull('ends_at')
                ->selectRaw('project_job_assignment_id,
                    COALESCE(SUM(GREATEST(0, LEAST(TIMESTAMPDIFF(MINUTE, starts_at, ends_at), 1440)
                        - COALESCE(interruption_minutes, 0))), 0) as total')
                ->groupBy('project_job_assignment_id')
                ->pluck('total', 'project_job_assignment_id')
                ->toArray();
            foreach ($proofAssignmentIds as $proofId) {
                $total = (int)($rawProofMinutes[$proofId] ?? 0);
                foreach ($supersedingProofPjas[$proofId] ?? [] as $wId) {
                    $total += (int)($rawProofMinutes[$wId] ?? 0);
                }
                $proofEvtMinutes[$proofId] = $total;
            }
        }

        $cells = $cells->map(function ($c) use ($evtMinutes, $proofEvtMinutes) {
            if ($c['assignment_id'] && isset($evtMinutes[$c['assignment_id']])) {
                $c['work_minutes'] = $evtMinutes[$c['assignment_id']];
            }
            $c['proof_work_minutes'] = $c['proof_assignment_id'] ? ($proofEvtMinutes[$c['proof_assignment_id']] ?? 0) : 0;
            return $c;
        });

        // 担当者選択用ユーザー一覧（案件メンバー + Coordinator + ゴーストユーザー）
        $memberIds = $projectJob->teamMembers()->pluck('user_id')->toArray();
        $coIds = $projectJob->coordinators->pluck('id')->toArray();
        $ownerId = $projectJob->user_id;
        $userIds = array_unique(array_merge($memberIds, $coIds, [$ownerId]));
        $regularUsers = User::whereIn('id', $userIds)->ordered()->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'is_ghost' => false]);
        $ghostUsers = \App\Models\User::withGhosts()
            ->where('ghost_owner_id', $request->user()->id)
            ->ordered()->get(['id', 'name'])
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'is_ghost' => true]);
        $users = $regularUsers->concat($ghostUsers)->values();

        // ログインCoordinatorが管理する外注先
        $authUser = $request->user();
        $subcontractors = \App\Models\Subcontractor::managedBy($authUser->id)
            ->get(['id', 'name'])
            ->map(fn($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'is_subcontractor' => true,
            ]);

        // 列タイプ用マスターデータ
        $stages = \App\Models\Stage::orderBy('id')->get(['id', 'name']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']);
        $assignments = \App\Models\Assignment::orderBy('name')->get(['id', 'name', 'code']);
        $workItemTypes = \App\Models\WorkItemType::orderBy('id')->get(['id', 'name', 'group']);

        // テンプレート一覧（シート作成モーダル用）
        $userId = $request->user()->id;
        $templates = ProgressTemplate::where('is_shared', true)
            ->orWhere('created_by', $userId)
            ->orderByDesc('updated_at')
            ->get(['id', 'name']);

        // workerセル用: 同案件のスケジュール一覧
        $projectSchedules = \App\Models\ProjectSchedule::where('project_job_id', $projectJob->id)
            ->orderBy('start_date')
            ->orderBy('name')
            ->get(['id', 'name', 'start_date', 'end_date'])
            ->map(fn($s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'end_date' => $s->end_date?->format('Y-m-d'),
            ]);

        return Inertia::render('Coordinator/ProgressSheets/Show', [
            'sheet'      => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $sheet->column_config,
                'created_by'    => $sheet->created_by,
                'share_token'   => $sheet->share_token,
            ],
            'rows'             => $rows,
            'cells'            => $cells,
            'projectSchedules' => $projectSchedules,
            'users'          => $users,
            'subcontractors' => $subcontractors,
            'stages'      => $stages,
            'sizes'       => $sizes,
            'assignments' => $assignments,
            'workItemTypes' => $workItemTypes,
            'projectJob'  => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
                'client_id'   => $projectJob->client?->id,
                'size_id'     => $projectJob->size_id,
                'size_name'   => $projectJob->size?->name,
                'page_count'  => $projectJob->page_count,
            ],
            'canEdit'    => $canEdit,
            'templates'  => $templates,
        ]);
    }

    /**
     * シートの列構成・名前を更新（編集モード）
     */
    public function update(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'column_config' => 'sometimes|required|array',
        ]);

        $sheet->update($validated);

        return back()->with('success', '進行管理表を更新しました。');
    }

    /**
     * シートを削除
     */
    public function destroy(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $projectJobId = $sheet->project_job_id;
        $sheet->delete();

        return redirect()->route('coordinator.project_jobs.show', $projectJobId)
            ->with('success', '進行管理表を削除しました。');
    }

    /**
     * シートのcolumn_configをテンプレートとして登録
     */
    public function registerAsTemplate(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        ProgressTemplate::create([
            'name'          => $validated['name'],
            'column_config' => $sheet->column_config,
            'created_by'    => $request->user()->id,
            'is_shared'     => true,
        ]);

        return back()->with('success', 'テンプレートとして登録しました。');
    }

    /**
     * セルにジョブを紐付けて登録（自分 → MyJob / 他者 → Coordinator割当）
     */
    public function linkJob(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();
        $this->authorizeJobAccess($user, $sheet->projectJob, $sheet);

        $validated = $request->validate([
            'row_id'                    => 'required|integer',
            'col_key'                   => 'required|string',
            'title'                     => 'required|string|max:255',
            'detail'                    => 'nullable|string',
            'desired_end_date'          => 'nullable|date',
            'assignee_user_id'          => 'nullable|integer|exists:users,id',
            'assignee_subcontractor_id' => 'nullable|integer|exists:subcontractors,id',
        ]);

        $allowedRowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id')->toArray();
        abort_unless(in_array($validated['row_id'], $allowedRowIds), 403);

        $senderId        = $user->id;
        $subcontractorId = null;

        if (!empty($validated['assignee_subcontractor_id'])) {
            // 外注先への依頼：user_id はコーディネーター自身、subcontractor_id に外注先を保存
            $assigneeId      = $user->id;
            $subcontractorId = $validated['assignee_subcontractor_id'];
        } else {
            $assigneeId = $validated['assignee_user_id'] ?? $user->id;
        }

        DB::transaction(function () use ($validated, $sheet, $assigneeId, $senderId, $subcontractorId) {
            $assignment = ProjectJobAssignment::create([
                'project_job_id'   => $sheet->project_job_id,
                'user_id'          => $assigneeId,
                'sender_id'        => $senderId,
                'subcontractor_id' => $subcontractorId,
                'title'            => $validated['title'],
                'detail'           => $validated['detail'] ?? null,
                'desired_end_date' => $validated['desired_end_date'] ?? null,
            ]);

            ProgressCell::updateOrCreate(
                ['row_id' => $validated['row_id'], 'col_key' => $validated['col_key']],
                [
                    'assignment_id'          => $assignment->id,
                    'value_user_id'          => $subcontractorId ? null : $assigneeId,
                    'value_subcontractor_id' => $subcontractorId,
                ]
            );
        });

        return back()->with('success', 'ジョブを登録しました。');
    }

    /**
     * セルの登録情報（assignment_id）を削除する
     * イベントがなければ assignment も削除する（救済措置含む）
     */
    public function unlinkJob(Request $request, ProgressSheet $sheet): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $this->authorizeJobAccess($user, $sheet->projectJob, $sheet);

        $validated = $request->validate([
            'row_id' => 'required|integer',
            'col_key' => 'required|string',
        ]);

        DB::transaction(function () use ($validated) {
            $cell = ProgressCell::where('row_id', $validated['row_id'])
                ->where('col_key', $validated['col_key'])
                ->first();

            if (!$cell) {
                return;
            }

            $assignmentId = $cell->assignment_id;

            // セルの assignment_id をクリア
            $cell->assignment_id = null;
            $cell->save();

            if (!$assignmentId) {
                return;
            }

            $assignment = ProjectJobAssignment::find($assignmentId);
            if (!$assignment) {
                // 孤立参照（Sakura救済措置）: セルのクリアのみで完了
                return;
            }

            // イベントがなければ assignment も削除
            $hasEvents = false;
            try {
                $hasEvents = \Illuminate\Support\Facades\Schema::hasColumn('events', 'project_job_assignment_id')
                    && \App\Models\Event::where('project_job_assignment_id', $assignmentId)->exists();
            } catch (\Throwable $e) {
                // ignore
            }

            if (!$hasEvents) {
                $assignment->delete();
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * ジョブに直接「校了」マークをつける（校正管理を通さないルート用）
     */
    public function proofDirectComplete(Request $request, ProjectJobAssignment $assignment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = in_array($user->user_role, ['admin', 'superadmin', 'coordinator', 'clerk']);
        abort_unless($isAdmin, 403);

        $assignment->update([
            'proof_completed_at' => now(),
            'completed'          => true,
        ]);

        return response()->json(['success' => true]);
    }

    // ───── helpers ─────

    private function authorizeJobAccess(User $user, ProjectJob $projectJob, ?ProgressSheet $sheet = null): void
    {
        $isOwner   = $projectJob->user_id === $user->id;
        $isSub     = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        $isCreator = $sheet && $sheet->created_by === $user->id;

        abort_unless($isOwner || $isSub || $isAdmin || $isCreator, 403);
    }

    private function canEdit(User $user, ProjectJob $projectJob, ?ProgressSheet $sheet = null): bool
    {
        $isOwner   = $projectJob->user_id === $user->id;
        $isSub     = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        $isCreator = $sheet && $sheet->created_by === $user->id;

        return $isOwner || $isSub || $isAdmin || $isCreator;
    }

    /**
     * 管理者がアサインメントを「完了」に変更する
     */
    public function completeAssignment(Request $request, ProjectJobAssignment $assignment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        // 管理者・担当コーディネーター・本人のみ許可
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin', 'coordinator', 'clerk', 'leader']);
        $isOwner = $assignment->user_id === $user->id;
        abort_unless($isAdmin || $isOwner, 403);

        $assignment->completed = true;
        $this->setCompletedStatus($assignment);
        $assignment->save();

        // workerセルの completed_at を記録
        try {
            \App\Models\ProgressCell::where('assignment_id', $assignment->id)
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);
        } catch (\Throwable $__e) {
            // non-fatal
        }

        // ジョブ通知（進行管理表リンクあり → リーダー/サブCoへ）
        try {
            $pj = $assignment->projectJob
                ?? \App\Models\ProjectJob::find($assignment->project_job_id);
            if ($pj) {
                $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->exists();
                if ($hasProgressLink) {
                    \App\Services\JobNotificationService::notifyProgressCompleted($user, $pj, $assignment);
                }
            }
        } catch (\Throwable $__eNotify) {
            \Illuminate\Support\Facades\Log::warning('ProgressSheetController: completeAssignment notification error', ['error' => $__eNotify->getMessage()]);
        }

        // イベントも完了にする（進行表→イベント同期）
        try {
            // coordinator割当 OR それを supersedes している by_myself割当 に紐づくイベントを完了
            $eventAssignmentIds = [$assignment->id];
            $supersedingIds = ProjectJobAssignment::where('supersedes_assignment_id', $assignment->id)
                ->pluck('id')
                ->toArray();
            $eventAssignmentIds = array_merge($eventAssignmentIds, $supersedingIds);
            $prefix = '【完了】';
            $eventsToComplete = \App\Models\Event::whereIn(
                'project_job_assignment_id',
                array_unique(array_filter($eventAssignmentIds))
            )->get();
            foreach ($eventsToComplete as $evt) {
                if (strpos($evt->title, $prefix) !== 0) {
                    $evt->title = $prefix . $evt->title;
                    $evt->save();
                }
            }
        } catch (\Throwable $__eEvt) {
            // non-fatal
        }

        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
    }

    /**
     * 管理者がアサインメントを「未完了」に変更する
     */
    public function uncompleteAssignment(Request $request, ProjectJobAssignment $assignment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = in_array($user->user_role, ['admin', 'superadmin', 'coordinator', 'clerk', 'leader']);
        abort_unless($isAdmin, 403);

        $assignment->completed = false;
        $assignment->status_id = null;
        $assignment->save();

        // 進行表セルの completed_at もクリア
        try {
            \App\Models\ProgressCell::where('assignment_id', $assignment->id)
                ->whereNotNull('completed_at')
                ->update(['completed_at' => null]);
        } catch (\Throwable $__e) {
            // non-fatal
        }

        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
    }

    /** column_config ツリーからリーフノードを収集して「パス付きラベル」で返す */
    private function collectLeaves(array $nodes, string $prefix = ''): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $label = $prefix
                ? $prefix . ' > ' . ($node['label'] ?? $node['key'])
                : ($node['label'] ?? $node['key']);
            if (empty($node['children'])) {
                $result[] = [
                    'key'        => $node['key'],
                    'path_label' => $label,
                    'type'       => $node['type'] ?? 'text',
                ];
            } else {
                $result = array_merge($result, $this->collectLeaves($node['children'], $label));
            }
        }
        return $result;
    }

    private function setCompletedStatus(ProjectJobAssignment $assignment): void
    {
        try {
            $status = \Illuminate\Support\Facades\DB::table('statuses')
                ->where('key', 'completed')
                ->orWhere('slug', 'completed')
                ->first();
            if (!$status) {
                $statusId = \Illuminate\Support\Facades\DB::table('statuses')->insertGetId([
                    'key' => 'completed', 'slug' => 'completed', 'name' => '完了',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $statusId = $status->id;
            }
            $assignment->status_id = $statusId;
        } catch (\Throwable $e) {
            // status_id 更新失敗は無視（completed フラグのみ更新）
        }
    }

    /**
     * 印刷専用ページ（Coordinator認証）
     */
    public function printView(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);
        $sheet->load(['projectJob.client', 'projectJob.size']);
        $projectJob = $sheet->projectJob;

        $rows  = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order', 'parent_id']);
        $cells = $this->buildPrintCells($rows->pluck('id'));

        return \Inertia\Inertia::render('Shared/ProgressSheets/Print', [
            'sheet'      => ['id' => $sheet->id, 'name' => $sheet->name, 'column_config' => $sheet->column_config],
            'rows'       => $rows,
            'cells'      => $cells,
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
                'size_name'   => $projectJob->size?->name,
                'page_count'  => $projectJob->page_count,
            ],
        ]);
    }

    private function buildPrintCells($rowIds): \Illuminate\Support\Collection
    {
        return ProgressCell::whereIn('row_id', $rowIds)
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,desired_end_date,completed',
                'schedule:id,name,end_date,completed_at',
                'noteUser:id,name,user_role',
            ])
            ->get()
            ->map(fn($c) => [
                'id'                       => $c->id,
                'row_id'                   => $c->row_id,
                'col_key'                  => $c->col_key,
                'cell_type'                => $c->cell_type,
                'value_text'               => $c->value_text,
                'value_date'               => $c->value_date?->format('Y-m-d'),
                'value_bool'               => $c->value_bool,
                'value_user_id'            => $c->value_user_id,
                'value_user_name'          => $c->valueUser?->name,
                'value_subcontractor_id'   => $c->value_subcontractor_id,
                'value_subcontractor_name' => $c->valueSubcontractor?->name,
                'assignment_id'            => null,
                'assignment_completed'     => $c->completed_at !== null || ($c->assignment?->completed ?? false),
                'assignment_end_date'      => $c->assignment?->desired_end_date?->format('Y-m-d'),
                'proof_assignment_id'      => null,
                'proof_assignment_completed' => false,
                'schedule_id'              => $c->schedule_id,
                'schedule_name'            => $c->schedule?->name,
                'schedule_end_date'        => $c->schedule?->end_date?->format('Y-m-d'),
                'schedule_completed_at'    => $c->schedule?->completed_at?->format('Y-m-d H:i:s'),
                'cell_deadline'            => $c->cell_deadline?->format('Y-m-d'),
                'cell_note'                => $c->cell_note,
                'cell_note_user_name'      => $c->noteUser?->name,
                'cell_note_user_role'      => $c->noteUser?->user_role,
                'completed_at'             => $c->completed_at?->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 共有トークンを発行してURLを返す
     */
    public function share(Request $request, ProgressSheet $sheet): \Illuminate\Http\JsonResponse
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

        if (!$sheet->share_token) {
            $sheet->share_token = \Illuminate\Support\Str::random(64);
            $sheet->save();
        }

        return response()->json(['share_token' => $sheet->share_token]);
    }

    /**
     * 共有トークンを無効化する
     */
    public function unshare(Request $request, ProgressSheet $sheet): \Illuminate\Http\JsonResponse
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

        $sheet->share_token = null;
        $sheet->save();

        return response()->json(['success' => true]);
    }

    /**
     * 既存シートの user+joblink ペアを変換プレビュー（読み取り専用・変換しない）
     */
    public function convertPreview(Request $request, ProgressSheet $sheet): \Illuminate\Http\JsonResponse
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

        $pairs  = $this->detectUserJobPairs($sheet->column_config ?? []);
        $rowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id');

        $result            = [];
        $totalDataCells    = 0;
        $totalUnmigratable = 0;

        foreach ($pairs as $pair) {
            $userColKey    = $pair['user_key'];
            $joblinkColKey = $pair['joblink_key'];

            $userCellsWithData = ProgressCell::whereIn('row_id', $rowIds)
                ->where('col_key', $userColKey)
                ->where(function ($q) {
                    $q->whereNotNull('value_user_id')
                      ->orWhereNotNull('value_subcontractor_id');
                })
                ->count();

            $joblinkCellsWithData = ProgressCell::whereIn('row_id', $rowIds)
                ->where('col_key', $joblinkColKey)
                ->whereNotNull('assignment_id')
                ->count();

            $totalDataCells += $userCellsWithData + $joblinkCellsWithData;

            $result[] = [
                'parent_label'       => $pair['parent_label'],
                'source_type'        => $pair['source_type'] ?? 'user',
                'user_col_key'       => $userColKey,
                'joblink_col_key'    => $joblinkColKey,
                'worker_col_key'     => $userColKey,
                'cells_with_user'    => $userCellsWithData,
                'cells_with_job'     => $joblinkCellsWithData,
                'cells_unmigratable' => 0,
            ];
        }

        return response()->json([
            'pairs'              => $result,
            'total_pairs'        => count($result),
            'total_data_cells'   => $totalDataCells,
            'total_unmigratable' => $totalUnmigratable,
        ]);
    }

    /**
     * 既存シートの user+joblink ペアを worker 型に変換する（不可逆）
     */
    public function convertToV2(Request $request, ProgressSheet $sheet): \Illuminate\Http\JsonResponse
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

        $pairs = $this->detectUserJobPairs($sheet->column_config ?? []);

        if (empty($pairs)) {
            return response()->json(['message' => '変換対象のペアが見つかりませんでした。'], 422);
        }

        $rowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id');

        DB::transaction(function () use ($pairs, $rowIds, $sheet) {
            foreach ($pairs as $pair) {
                $userColKey    = $pair['user_key'];
                $joblinkColKey = $pair['joblink_key'];

                // joblink セルの assignment_id を、同 row_id の user セルにコピー
                $joblinkCells = ProgressCell::whereIn('row_id', $rowIds)
                    ->where('col_key', $joblinkColKey)
                    ->whereNotNull('assignment_id')
                    ->with('assignment:id,user_id')
                    ->get(['id', 'row_id', 'assignment_id']);

                foreach ($joblinkCells as $jc) {
                    $userCell = ProgressCell::firstOrNew([
                        'row_id'  => $jc->row_id,
                        'col_key' => $userColKey,
                    ]);
                    // user セル側に assignment_id が未設定の場合のみコピー（既存データ保護）
                    if (!$userCell->assignment_id) {
                        $userCell->assignment_id = $jc->assignment_id;
                        $userCell->value_user_id = $jc->assignment?->user_id;
                        $userCell->save();
                    }
                }

                // joblink セルを削除（assignment_id は user セルに移送済み）
                ProgressCell::whereIn('row_id', $rowIds)
                    ->where('col_key', $joblinkColKey)
                    ->delete();
            }

            // column_config を変換
            $newConfig            = $this->transformColumnConfig($sheet->column_config ?? [], $pairs);
            $sheet->column_config = $newConfig;
            $sheet->save();
        });

        return response()->json(['success' => true]);
    }

    /**
     * column_config ツリーを再帰走査し、user+joblink の連続ペアを検出する
     *
     * @return array [['parent_label'=>string, 'user_key'=>string, 'joblink_key'=>string], ...]
     */
    private function detectUserJobPairs(array $nodes, string $parentLabel = ''): array
    {
        $pairs = [];
        foreach ($nodes as $node) {
            $label    = $node['label'] ?? $node['key'] ?? '';
            $children = $node['children'] ?? [];

            if (!empty($children)) {
                // 直接の children で user→joblink の連続ペアを探す
                for ($i = 0; $i < count($children) - 1; $i++) {
                    $srcType = $children[$i]['type'] ?? '';
                    if (($srcType === 'user' || $srcType === 'proof_user')
                        && ($children[$i + 1]['type'] ?? '') === 'joblink'
                    ) {
                        $pairs[] = [
                            'parent_label' => $label,
                            'user_key'     => $children[$i]['key'],
                            'joblink_key'  => $children[$i + 1]['key'],
                            'source_type'  => $srcType,
                        ];
                        $i++; // joblink ノードをスキップ
                    }
                }
                // 再帰（より深い階層も検出）
                $pairs = array_merge($pairs, $this->detectUserJobPairs($children, $label));
            }
        }
        return $pairs;
    }

    /**
     * column_config ツリーを変換（user+joblink ペア → worker 型）
     */
    private function transformColumnConfig(array $nodes, array $pairs): array
    {
        // user_key → joblink_key のマップ / user_key → source_type のマップ
        $pairMap        = [];
        $pairSourceType = [];
        foreach ($pairs as $pair) {
            $pairMap[$pair['user_key']]        = $pair['joblink_key'];
            $pairSourceType[$pair['user_key']] = $pair['source_type'] ?? 'user';
        }
        $joblinkKeys = array_values($pairMap);

        $result = [];
        $i      = 0;
        while ($i < count($nodes)) {
            $node     = $nodes[$i];
            $type     = $node['type'] ?? 'text';
            $children = $node['children'] ?? [];

            if (($type === 'user' || $type === 'proof_user') && isset($pairMap[$node['key']])) {
                // user → worker に変換 / proof_user → proof_v2 に変換（V2統合型）
                $targetType = ($pairSourceType[$node['key']] === 'proof_user') ? 'proof_v2' : 'worker';
                $result[] = [
                    'key'   => $node['key'],
                    'label' => $node['label'] ?? '担当',
                    'type'  => $targetType,
                ];
                if (isset($nodes[$i + 1]) && ($nodes[$i + 1]['type'] ?? '') === 'joblink') {
                    $i++;
                }
            } elseif ($type === 'joblink' && in_array($node['key'], $joblinkKeys)) {
                // joblink ペア側が単独で現れた場合 → スキップ
            } elseif (!empty($children)) {
                $newChildren = $this->transformColumnConfig($children, $pairs);

                // 元 children が user/proof_user+joblink の2つのみ → 変換後1つ → 親ごと置き換え
                $wasOnlyPair = count($children) === 2
                    && in_array($children[0]['type'] ?? '', ['user', 'proof_user'])
                    && ($children[1]['type'] ?? '') === 'joblink';

                if ($wasOnlyPair && count($newChildren) === 1) {
                    $result[] = [
                        'key'   => $newChildren[0]['key'],
                        'label' => $node['label'] ?? $newChildren[0]['label'],
                        'type'  => $newChildren[0]['type'],
                    ];
                } else {
                    $node['children'] = $newChildren;
                    $result[]         = $node;
                }
            } else {
                $result[] = $node;
            }

            $i++;
        }

        return $result;
    }
}
