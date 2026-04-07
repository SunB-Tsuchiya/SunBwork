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
            'name'          => 'required|string|max:255',
            'template_id'   => 'nullable|exists:progress_templates,id',
            'column_config' => 'nullable|array',
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

        $cells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with(['valueUser:id,name', 'valueSubcontractor:id,name', 'assignment:id,title,detail,desired_end_date,completed,user_id,sender_id,subcontractor_id'])
            ->get()
            ->map(fn($c) => [
                'id'                         => $c->id,
                'row_id'                     => $c->row_id,
                'col_key'                    => $c->col_key,
                'value_text'                 => $c->value_text,
                'value_date'                 => $c->value_date?->format('Y-m-d'),
                'value_bool'                 => $c->value_bool,
                'value_user_id'              => $c->value_user_id,
                'value_user_name'            => $c->valueUser?->name,
                'value_subcontractor_id'     => $c->value_subcontractor_id,
                'value_subcontractor_name'   => $c->valueSubcontractor?->name,
                'assignment_id'              => $c->assignment_id,
                'assignment_title'           => $c->assignment?->title,
                'assignment_completed'       => $c->assignment?->completed,
                'assignment_user_id'         => $c->assignment?->user_id,
                'assignment_subcontractor_id' => $c->assignment?->subcontractor_id,
                'assignment_end_date'        => $c->assignment?->desired_end_date?->format('Y-m-d'),
            ]);

        // 担当者選択用ユーザー一覧（案件メンバー + Coordinator）
        $memberIds = $projectJob->teamMembers()->pluck('user_id')->toArray();
        $coIds = $projectJob->coordinators->pluck('id')->toArray();
        $ownerId = $projectJob->user_id;
        $userIds = array_unique(array_merge($memberIds, $coIds, [$ownerId]));
        $users = User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);

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

        return Inertia::render('Coordinator/ProgressSheets/Show', [
            'sheet'      => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $sheet->column_config,
                'created_by'    => $sheet->created_by,
            ],
            'rows'        => $rows,
            'cells'       => $cells,
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
            'row_id'           => 'required|integer',
            'col_key'          => 'required|string',
            'title'            => 'required|string|max:255',
            'detail'           => 'nullable|string',
            'desired_end_date' => 'nullable|date',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $allowedRowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id')->toArray();
        abort_unless(in_array($validated['row_id'], $allowedRowIds), 403);

        $assigneeId = $validated['assignee_user_id'] ?? $user->id;
        $senderId   = $user->id;

        DB::transaction(function () use ($validated, $sheet, $assigneeId, $senderId) {
            $assignment = ProjectJobAssignment::create([
                'project_job_id'   => $sheet->project_job_id,
                'user_id'          => $assigneeId,
                'sender_id'        => $senderId,
                'title'            => $validated['title'],
                'detail'           => $validated['detail'] ?? null,
                'desired_end_date' => $validated['desired_end_date'] ?? null,
            ]);

            ProgressCell::updateOrCreate(
                ['row_id' => $validated['row_id'], 'col_key' => $validated['col_key']],
                ['assignment_id' => $assignment->id]
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
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin', 'coordinator', 'clerk']);
        $isOwner = $assignment->user_id === $user->id;
        abort_unless($isAdmin || $isOwner, 403);

        $assignment->completed = true;
        $this->setCompletedStatus($assignment);
        $assignment->save();

        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
    }

    /**
     * 管理者がアサインメントを「未完了」に変更する
     */
    public function uncompleteAssignment(Request $request, ProjectJobAssignment $assignment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = in_array($user->user_role, ['admin', 'superadmin', 'coordinator', 'clerk']);
        abort_unless($isAdmin, 403);

        $assignment->completed = false;
        $assignment->save();

        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
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
}
