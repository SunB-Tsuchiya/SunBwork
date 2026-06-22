<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProgressCell;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProgressSheetController extends Controller
{
    /**
     * 進行管理表の閲覧（User 向け）
     */
    public function show(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();
        $sheet->load(['projectJob.client', 'projectJob.size', 'projectJob.coordinators']);
        $projectJob = $sheet->projectJob;

        // アクセス確認：案件メンバー・割当ユーザー・オーナー・サブCo・管理者
        $this->authorizeView($user, $projectJob);

        $rows = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order', 'parent_id']);

        $cells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,title,detail,desired_end_date,completed,proof_completed_at,user_id,sender_id',
                'assignment.user:id,name',
                'proofAssignment:id,title,completed,proof_completed_at,user_id,sender_id',
                'schedule:id,name,end_date,completed_at',
            ])
            ->get()
            ->map(fn ($c) => [
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
                'schedule_id'                 => $c->schedule_id,
                'schedule_name'               => $c->schedule?->name,
                'schedule_completed_at'       => $c->schedule?->completed_at?->format('Y-m-d H:i:s'),
                'cell_deadline'               => $c->cell_deadline?->format('Y-m-d'),
                'completed_at'                => $c->completed_at?->format('Y-m-d H:i:s'),
                'assignment_id'               => $c->assignment_id,
                'assignment_title'            => $c->assignment?->title,
                'assignment_completed'        => $c->assignment?->completed,
                'assignment_proof_completed'  => $c->assignment?->proof_completed_at !== null,
                'assignment_user_id'          => $c->assignment?->user_id,
                'assignment_end_date'         => $c->assignment?->desired_end_date?->format('Y-m-d'),
                'proof_assignment_id'         => $c->proof_assignment_id,
                'proof_assignment_title'      => $c->proofAssignment?->title,
                'proof_assignment_completed'  => $c->proofAssignment?->completed,
            ]);

        // ユーザー名解決用（案件メンバー + Coordinator + オーナー）
        $memberIds = $projectJob->teamMembers()->pluck('user_id')->toArray();
        $coIds     = $projectJob->coordinators->pluck('id')->toArray();
        $ownerId   = $projectJob->user_id;
        $userIds   = array_unique(array_merge($memberIds, $coIds, [$ownerId]));
        $users     = User::whereIn('id', $userIds)->ordered()->get(['id', 'name']);

        // カレンダーからのドラッグ選択で遷移した場合、時間プリフィルを引き継ぐ
        $calendarPrefill = null;
        $calDate    = $request->query('date');
        $startHour  = $request->query('startHour');
        $startMin   = $request->query('startMinute');
        $endHour    = $request->query('endHour');
        $endMin     = $request->query('endMinute');
        if ($calDate || $startHour) {
            $calendarPrefill = [
                'date'        => $calDate,
                'startHour'   => $startHour,
                'startMinute' => $startMin,
                'endHour'     => $endHour,
                'endMinute'   => $endMin,
            ];
        }

        return Inertia::render('User/ProgressSheets/Show', [
            'sheet'      => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $sheet->column_config ?? [],
                'rows'          => $rows,
                'cells'         => $cells,
            ],
            'users'      => $users,
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title ?? $projectJob->name ?? '-',
                'client_id'   => $projectJob->client_id ?? null,
                'client_name' => $projectJob->client?->name ?? null,
                'size_name'   => $projectJob->size?->name ?? null,
                'page_count'  => $projectJob->page_count ?? null,
            ],
            'calendarPrefill' => $calendarPrefill,
        ]);
    }

    /**
     * 印刷専用ページ（User認証）
     */
    public function printView(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();
        $sheet->load(['projectJob.client', 'projectJob.size', 'projectJob.coordinators']);
        $projectJob = $sheet->projectJob;
        $this->authorizeView($user, $projectJob);

        $rows  = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order', 'parent_id']);
        $cells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,desired_end_date,completed,user_id',
                'assignment.user:id,name',
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
                'value_user_id'            => $c->value_user_id ?? $c->assignment?->user_id,
                'value_user_name'          => $c->valueUser?->name ?? $c->assignment?->user?->name,
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

        return Inertia::render('Shared/ProgressSheets/Print', [
            'sheet'      => ['id' => $sheet->id, 'name' => $sheet->name, 'column_config' => $sheet->column_config ?? []],
            'rows'       => $rows,
            'cells'      => $cells,
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title ?? '-',
                'client_name' => $projectJob->client?->name ?? null,
                'size_name'   => $projectJob->size?->name ?? null,
                'page_count'  => $projectJob->page_count ?? null,
            ],
        ]);
    }

    /**
     * セルにMyJobを紐付けて登録（自己割当のみ）
     */
    public function linkJob(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();
        $this->authorizeView($user, $sheet->projectJob);

        $validated = $request->validate([
            'row_id'           => 'required|integer',
            'col_key'          => 'required|string',
            'title'            => 'required|string|max:255',
            'detail'           => 'nullable|string',
            'desired_end_date' => 'nullable|date',
        ]);

        $allowedRowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id')->toArray();
        abort_unless(in_array($validated['row_id'], $allowedRowIds), 403);

        $createdAssignment = null;
        DB::transaction(function () use ($validated, $sheet, $user, &$createdAssignment) {
            $assignment = ProjectJobAssignment::create([
                'project_job_id'   => $sheet->project_job_id,
                'user_id'          => $user->id,
                'sender_id'        => $user->id,
                'title'            => $validated['title'],
                'detail'           => $validated['detail'] ?? null,
                'desired_end_date' => $validated['desired_end_date'] ?? null,
            ]);

            $cell = ProgressCell::updateOrCreate(
                ['row_id' => $validated['row_id'], 'col_key' => $validated['col_key']],
                [
                    'assignment_id' => $assignment->id,
                    'value_user_id' => $user->id,
                ]
            );

            // ジョブ側にも progress_cell_id を設定（双方向紐づけ）
            $assignment->update(['progress_cell_id' => $cell->id]);

            $createdAssignment = $assignment;
        });

        if ($createdAssignment) {
            $projectJob = $sheet->projectJob ?? \App\Models\ProjectJob::find($sheet->project_job_id);
            if ($projectJob) {
                \App\Services\JobNotificationService::notifyProgressRegistered($user, $projectJob, $createdAssignment);
            }
        }

        return back()->with('success', 'MyJobに登録しました。');
    }

    /**
     * User が担当者セルに自分を登録（MyJob化）
     */
    public function assign(Request $request, ProgressSheet $sheet, ProgressCell $cell)
    {
        $user      = $request->user();
        $projectJob = $sheet->projectJob;
        $this->authorizeView($user, $projectJob);

        // cell が sheet に属することを確認
        $row = ProgressRow::where('id', $cell->row_id)
            ->where('sheet_id', $sheet->id)
            ->firstOrFail();

        DB::transaction(function () use ($cell, $user, $projectJob, $row, $sheet) {
            // 既に自分が登録済みなら何もしない
            if ($cell->value_user_id === $user->id) {
                return;
            }

            // col_key に対応するラベルを column_config から取得
            $colLabel = $this->findColLabel($sheet->column_config ?? [], $cell->col_key);

            // MyJob用に project_job_assignments にレコードを作成
            $assignment = ProjectJobAssignment::create([
                'project_job_id'   => $projectJob->id,
                'user_id'          => $user->id,
                'sender_id'        => $user->id,
                'title'            => $projectJob->title . ' - ' . $row->label . '/' . $colLabel,
                'progress_cell_id' => $cell->id,
            ]);

            $cell->update([
                'value_user_id' => $user->id,
                'assignment_id' => $assignment->id,
            ]);
        });

        return back()->with('success', '自分を担当者として登録しました。');
    }

    /**
     * User が担当者登録を解除
     */
    public function unassign(Request $request, ProgressSheet $sheet, ProgressCell $cell)
    {
        $user = $request->user();
        $this->authorizeView($user, $sheet->projectJob);

        // 自分の登録のみ解除可能
        abort_unless($cell->value_user_id === $user->id, 403);

        ProgressRow::where('id', $cell->row_id)
            ->where('sheet_id', $sheet->id)
            ->firstOrFail();

        $cell->update([
            'value_user_id' => null,
            'assignment_id' => null,
        ]);

        return back()->with('success', '担当者登録を解除しました。');
    }

    // ─────

    private function authorizeView(User $user, $projectJob): void
    {
        $isOwner    = $projectJob->user_id === $user->id;
        $isSub      = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isMember   = $projectJob->teamMembers()->where('user_id', $user->id)->exists();
        $isAdmin    = in_array($user->user_role, ['admin', 'superadmin']);
        $isAssigned = ProjectJobAssignment::where('project_job_id', $projectJob->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('sender_id', $user->id);
            })->exists();

        abort_unless($isOwner || $isSub || $isMember || $isAdmin || $isAssigned, 403);
    }

    private function findColLabel(array $nodes, string $key): string
    {
        foreach ($nodes as $node) {
            if ($node['key'] === $key) {
                return $node['label'] ?? $key;
            }
            if (!empty($node['children'])) {
                $found = $this->findColLabel($node['children'], $key);
                if ($found !== $key) {
                    return $found;
                }
            }
        }
        return $key;
    }
}
