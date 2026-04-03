<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressSheet;
use App\Models\ProgressTemplate;
use App\Models\ProgressRow;
use App\Models\ProgressCell;
use App\Models\ProjectJob;
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
        ]);

        return redirect()->route('coordinator.progress_sheets.show', $sheet->id);
    }

    /**
     * シート詳細（進行管理表）
     */
    public function show(Request $request, ProgressSheet $sheet)
    {
        $sheet->load(['projectJob.client', 'projectJob.user', 'projectJob.coordinators']);
        $projectJob = $sheet->projectJob;

        $canEdit = $this->canEdit($request->user(), $projectJob);

        $rows = $sheet->rows()->get(['id', 'label', 'order']);

        $cells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with('valueUser:id,name')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'row_id'       => $c->row_id,
                'col_key'      => $c->col_key,
                'value_text'   => $c->value_text,
                'value_date'   => $c->value_date?->format('Y-m-d'),
                'value_bool'   => $c->value_bool,
                'value_user_id'=> $c->value_user_id,
                'value_user_name' => $c->valueUser?->name,
                'assignment_id'=> $c->assignment_id,
            ]);

        // 担当者選択用ユーザー一覧（案件メンバー + Coordinator）
        $memberIds = $projectJob->teamMembers()->pluck('user_id')->toArray();
        $coIds = $projectJob->coordinators->pluck('id')->toArray();
        $ownerId = $projectJob->user_id;
        $userIds = array_unique(array_merge($memberIds, $coIds, [$ownerId]));
        $users = User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);

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
            'rows'       => $rows,
            'cells'      => $cells,
            'users'      => $users,
            'projectJob' => [
                'id'    => $projectJob->id,
                'title' => $projectJob->title,
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
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

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
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

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
        $this->authorizeJobAccess($request->user(), $sheet->projectJob);

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

    // ───── helpers ─────

    private function authorizeJobAccess(User $user, ProjectJob $projectJob): void
    {
        $isOwner = $projectJob->user_id === $user->id;
        $isSub   = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);

        abort_unless($isOwner || $isSub || $isAdmin, 403);
    }

    private function canEdit(User $user, ProjectJob $projectJob): bool
    {
        $isOwner = $projectJob->user_id === $user->id;
        $isSub   = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);

        return $isOwner || $isSub || $isAdmin;
    }
}
