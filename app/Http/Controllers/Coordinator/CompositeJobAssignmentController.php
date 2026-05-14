<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CompositeJobAssignmentController extends Controller
{
    public function create(Request $request, ProjectJob $projectJob)
    {
        // 複合ジョブ作成は通常のアサイン作成に統合されました
        return redirect()->route('coordinator.project_jobs.assignments.create', ['projectJob' => $projectJob->id]);
    }

    /** @deprecated 旧複合ジョブ create（リダイレクト用に残置） */
    private function _legacyCreate(Request $request, ProjectJob $projectJob)
    {
        if ($projectJob->completed) {
            return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $projectJob->id])
                ->with('error', 'この案件は完了済みのため、新しいジョブを割り当てることはできません。');
        }

        $projectJob->load('client');

        $members = $projectJob->teamMembers()->with(['user', 'user.assignment'])->get()->map(function ($m) {
            return [
                'id'                    => $m->user?->id,
                'name'                  => $m->user?->name,
                'assignment_name'       => $m->user?->assignment?->name,
                'employment_type'       => $m->user?->employment_type ?? 'regular',
                'employment_type_label' => $m->user ? $m->user->employmentTypeLabel() : '',
                'is_ghost'              => false,
            ];
        })->filter(fn($item) => $item['id'] !== null)->values();
        $compositeSelfId = Auth::id();
        $ghosts = \App\Models\User::withGhosts()
            ->where('ghost_owner_id', $compositeSelfId)
            ->get(['id', 'name'])
            ->map(fn ($g) => [
                'id'                    => $g->id,
                'name'                  => $g->name,
                'assignment_name'       => null,
                'employment_type'       => 'regular',
                'employment_type_label' => '',
                'is_ghost'              => true,
            ]);
        $members = $members->concat($ghosts);

        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')
            ->get(['id', 'name', 'company_id', 'department_id']);

        $difficultySelect = ['id', 'name'];
        try {
            if (Schema::hasColumn('difficulties', 'slug')) $difficultySelect[] = 'slug';
        } catch (\Throwable $e) {}
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get($difficultySelect);

        $user = Auth::user();

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/CompositeCreate', [
            'projectJob'       => $projectJob,
            'members'          => $members,
            'types'            => $types,
            'sizes'            => $sizes,
            'stages'           => $stages,
            'difficulties'     => $difficulties,
            'user_role'        => $user->user_role ?? null,
            'user_company_id'  => $user->company_id ?? null,
            'user_department_id' => $user->department_id ?? null,
        ]);
    }

    public function store(Request $request, ProjectJob $projectJob)
    {
        if ($projectJob->completed) {
            return back()->withErrors(['error' => 'この案件は完了済みのため、新しいジョブを割り当てることはできません。']);
        }

        $validated = $request->validate([
            'user_id'           => 'nullable|exists:users,id',
            'title_suffix'      => 'nullable|string|max:255',
            'detail'            => 'nullable|string',
            'work_item_type_id' => 'nullable|exists:work_item_types,id',
            'stage_id'          => 'nullable|exists:stages,id',
            'difficulty_id'     => 'nullable|exists:difficulties,id',
            'desired_end_date'  => 'nullable|date',
            'desired_time'      => 'nullable|string|max:5',
            'estimated_hours'   => 'nullable|numeric|min:0',
            'file_info'         => 'nullable|string',
            'amounts'           => 'nullable|integer|min:0',
            'amounts_unit'      => 'nullable|in:page,file',
        ]);

        $fileInfo = null;
        if (!empty($validated['file_info'])) {
            $fileInfo = json_decode($validated['file_info'], true);
        }

        $title = trim(($projectJob->title ?? '') . ' ' . ($validated['title_suffix'] ?? ''));

        $assignment = ProjectJobAssignment::create([
            'project_job_id'    => $projectJob->id,
            'user_id'           => $validated['user_id'] ?: Auth::id(),
            'sender_id'         => Auth::id(),
            'title'             => $title,
            'detail'            => $validated['detail'] ?? null,
            'work_item_type_id' => $validated['work_item_type_id'] ?? null,
            'stage_id'          => $validated['stage_id'] ?? null,
            'difficulty_id'     => $validated['difficulty_id'] ?? null,
            'desired_end_date'  => $validated['desired_end_date'] ?? null,
            'desired_time'      => $validated['desired_time'] ?? null,
            'estimated_hours'   => $validated['estimated_hours'] ?? null,
            'amounts'           => $validated['amounts'] ?? null,
            'amounts_unit'      => $validated['amounts_unit'] ?? null,
            'job_type'          => 'composite',
            'file_info'         => $fileInfo,
        ]);

        // assignment_file_stats に upsert
        if ($fileInfo && is_array($fileInfo)) {
            \App\Models\AssignmentFileStat::upsertFromFileInfo($assignment->id, $fileInfo);
        }

        $senderUser = Auth::user();
        $targetUserId = $validated['user_id'] ?: Auth::id();

        // ジョブ通知
        try {
            \App\Services\JobNotificationService::notifyNewJob(
                $senderUser,
                $targetUserId,
                $projectJob,
                $assignment
            );
        } catch (\Throwable $e) {}

        // 保存して送信: JobAssignmentMessage を作成し assigned = true にする
        try {
            $bodyText = implode("\n", array_filter([
                '複合ジョブ割り当て依頼',
                '案件: ' . ($projectJob->title ?? ''),
                'ジョブ: ' . ($assignment->title ?? ''),
                $assignment->file_info
                    ? 'ファイル: ' . ($assignment->file_info['summary'] ?? ($assignment->file_info['total_files'] . 'ファイル'))
                    : null,
                !empty($validated['desired_end_date']) ? '締め切り: ' . $validated['desired_end_date'] : null,
            ]));

            $jam = \App\Models\JobAssignmentMessage::create([
                'project_job_assignment_id' => $assignment->id,
                'sender_id'                 => $senderUser->id,
                'subject'                   => $assignment->title,
                'body'                      => $bodyText,
            ]);

            $assignment->assigned = true;
            $assignment->save();

            // リアルタイム通知
            try {
                $jamLoaded = \App\Models\JobAssignmentMessage::with([
                    'sender',
                    'projectJobAssignment.user',
                    'projectJobAssignment.projectJob.client',
                ])->find($jam->id);
                event(new \App\Events\JobMessageCreated(
                    $jamLoaded,
                    [$targetUserId],
                    $jam->id
                ));
            } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('composite job send failed', ['error' => $e->getMessage()]);
        }

        return redirect()->route('coordinator.project_jobs.assignments.index', [
            'projectJob' => $projectJob->id,
        ])->with('success', '複合ジョブを作成・送信しました。');
    }
}
