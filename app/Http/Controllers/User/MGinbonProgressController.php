<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectTeamMember;
use App\Services\MGinbon\MGinbonPlannedActorPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MGinbonProgressController extends Controller
{
    /** 一般作業者が自分で選択する工程。日付だけの節目や文字入力は含めない。 */
    private const USER_SELECTABLE_STAGE_CODES = [
        'drawing',
        'initial_operation',
        'initial_check',
        'initial_text_proof',
        'reproof_operation',
        'reproof_scan_check',
        'reproof_text_proof',
        'third_operation',
        'third_proof',
        'client_return_operation',
        'fourth_operation',
        'fourth_proof',
        'fifth_operation',
    ];

    public function show(Request $request, ProjectJob $projectJob): Response
    {
        $this->authorizeProject($request, $projectJob);
        $project = MGinbonProject::query()->where('project_job_id', $projectJob->id)->firstOrFail();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);
        $filters = [
            'search' => trim((string) ($validated['search'] ?? '')),
            'media' => (string) ($validated['media'] ?? ''),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];

        $db = DB::connection('mginbon');
        $query = $db->table('mginbon_production_units as units')
            ->where('units.mginbon_project_id', $project->id)
            ->select([
                'units.id', 'units.unit_type', 'units.mikuni_code', 'units.n_code', 'units.display_name',
                'units.school_category', 'units.n_category',
            ]);
        if ($filters['search'] !== '') {
            $escaped = addcslashes($filters['search'], '\\%_');
            $query->where(fn ($q) => $q->where('units.display_name', 'like', "%{$escaped}%")
                ->orWhere('units.mikuni_code', 'like', "%{$escaped}%")
                ->orWhere('units.n_code', 'like', "%{$escaped}%"));
        }
        if ($filters['media'] !== '') {
            $query->whereExists(fn ($inner) => $inner->selectRaw('1')
                ->from('mginbon_items as filter_items')
                ->join('mginbon_media_types as filter_media', 'filter_media.id', '=', 'filter_items.mginbon_media_type_id')
                ->whereColumn('filter_items.mginbon_production_unit_id', 'units.id')
                ->where('filter_media.name', $filters['media']));
        }

        $units = $query
            ->orderByRaw('CASE WHEN units.mikuni_code REGEXP "^[0-9]+$" THEN CAST(units.mikuni_code AS UNSIGNED) ELSE 999999 END')
            ->orderBy('units.mikuni_code')->orderBy('units.display_name')
            ->paginate($filters['per_page'])->withQueryString();

        $unitIds = $units->getCollection()->pluck('id');
        $itemQuery = $db->table('mginbon_items as items')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->whereIn('items.mginbon_production_unit_id', $unitIds)
            ->select(['items.id', 'items.mginbon_production_unit_id as unit_id', 'media.name as media_name']);
        if ($filters['media'] !== '') $itemQuery->where('media.name', $filters['media']);
        $itemRows = $itemQuery->orderBy('media.sort_order')->orderBy('items.id')->get();
        $itemIds = $itemRows->pluck('id');
        $subjectRows = $db->table('mginbon_item_subjects as item_subjects')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->whereIn('item_subjects.mginbon_item_id', $itemIds)
            ->orderBy('subjects.sort_order')
            ->get(['item_subjects.id', 'item_subjects.mginbon_item_id', 'subjects.code', 'subjects.name']);
        $tasks = $db->table('mginbon_stage_tasks as tasks')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'tasks.mginbon_stage_definition_id')
            ->whereIn('tasks.mginbon_item_id', $itemIds)
            ->whereIn('stages.code', self::USER_SELECTABLE_STAGE_CODES)
            ->orderBy('stages.sort_order')
            ->get([
                'tasks.id', 'tasks.mginbon_item_id', 'tasks.mginbon_item_subject_id', 'tasks.status',
                'tasks.project_job_assignment_id', 'stages.id as stage_id', 'stages.code', 'stages.name',
                'stages.activity_type', 'stages.sort_order',
            ]);
        $assignments = ProjectJobAssignment::query()
            ->whereIn('id', $tasks->pluck('project_job_assignment_id')->filter()->unique())
            ->get(['id', 'user_id'])->keyBy('id');
        $userNames = \App\Models\User::query()->whereIn('id', $assignments->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');
        $plannedPackages = $db->table('mginbon_work_package_tasks as links')
            ->join('mginbon_work_packages as packages', 'packages.id', '=', 'links.mginbon_work_package_id')
            ->whereIn('links.mginbon_stage_task_id', $tasks->pluck('id'))
            ->where('packages.status', 'planned')
            ->orderByDesc('packages.id')
            ->get(['links.mginbon_stage_task_id', 'packages.user_id', 'packages.subcontractor_id'])
            ->groupBy('mginbon_stage_task_id')->map->first();
        $plannedUserNames = \App\Models\User::withGhosts()
            ->whereIn('id', $plannedPackages->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $plannedSubcontractorNames = \App\Models\Subcontractor::query()
            ->whereIn('id', $plannedPackages->pluck('subcontractor_id')->filter()->unique())->pluck('name', 'id');
        $tasksBySubject = $tasks->groupBy('mginbon_item_subject_id');
        $subjectsByItem = $subjectRows->groupBy('mginbon_item_id');
        $itemRows = $itemRows->map(function ($item) use ($subjectsByItem, $tasksBySubject, $assignments, $userNames, $plannedPackages, $plannedUserNames, $plannedSubcontractorNames, $request) {
            $item->subjects = $subjectsByItem->get($item->id, collect())->map(function ($subject) use ($tasksBySubject, $assignments, $userNames, $plannedPackages, $plannedUserNames, $plannedSubcontractorNames, $request) {
                $subject->tasks = $tasksBySubject->get($subject->id, collect())->map(function ($task) use ($assignments, $userNames, $plannedPackages, $plannedUserNames, $plannedSubcontractorNames, $request) {
                    $planned = $plannedPackages->get($task->id);
                    return [
                    'id' => $task->id,
                    'stage_id' => $task->stage_id,
                    'code' => $task->code,
                    'name' => $this->userStageName($task->code, $task->name),
                    'status' => $task->status,
                    'assignment_id' => $task->project_job_assignment_id,
                    'user_id' => $assignments->get($task->project_job_assignment_id)?->user_id,
                    'is_mine' => $assignments->get($task->project_job_assignment_id)?->user_id === $request->user()->id,
                    'user_name' => ($userId = $assignments->get($task->project_job_assignment_id)?->user_id)
                        ? ($userNames[$userId] ?? '担当者') : null,
                    'planned_user_id' => $planned?->user_id,
                    'planned_subcontractor_id' => $planned?->subcontractor_id,
                    'planned_actor_name' => $planned?->user_id
                        ? ($plannedUserNames[$planned->user_id] ?? '仮担当者')
                        : ($planned?->subcontractor_id ? ($plannedSubcontractorNames[$planned->subcontractor_id] ?? '仮外注先') : null),
                    ];
                })->values();
                return $subject;
            })->values();
            return $item;
        })->groupBy('unit_id');
        $units->setCollection($units->getCollection()->map(function ($unit) use ($itemRows) {
            $unit->items = $itemRows->get($unit->id, collect())->values();
            return $unit;
        }));

        return Inertia::render('User/MGinbon/Progress', [
            'project' => $project,
            'projectJob' => ['id' => $projectJob->id, 'title' => $projectJob->title],
            'units' => $units,
            'mediaOptions' => $db->table('mginbon_media_types')->where('is_active', true)
                ->select('name')->distinct()->orderBy('name')->pluck('name'),
            'filters' => $filters,
        ]);
    }

    public function register(Request $request, ProjectJob $projectJob, MGinbonPlannedActorPolicy $plannedActorPolicy): JsonResponse
    {
        $this->authorizeProject($request, $projectJob);
        $project = MGinbonProject::query()->where('project_job_id', $projectJob->id)->firstOrFail();
        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'stage_id' => ['required', 'integer'],
            'subject_ids' => ['required', 'array', 'min:1', 'max:4'],
            'subject_ids.*' => ['required', 'integer'],
            'replace_planned' => ['sometimes', 'boolean'],
        ]);

        $db = DB::connection('mginbon');
        $assignment = null;
        try {
            $result = $db->transaction(function () use ($request, $projectJob, $project, $validated, $db, $plannedActorPolicy, &$assignment) {
                $item = $db->table('mginbon_items as items')
                    ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
                    ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
                    ->where('items.id', $validated['item_id'])
                    ->where('units.mginbon_project_id', $project->id)
                    ->first(['items.id', 'units.display_name', 'media.name as media_name']);
                $stage = $db->table('mginbon_stage_definitions')->where('id', $validated['stage_id'])
                    ->where('mginbon_project_id', $project->id)->where('is_active', true)
                    ->whereIn('code', self::USER_SELECTABLE_STAGE_CODES)->first();
                abort_unless($item && $stage, 422, '対象の銀本作業が見つかりません。');

                $subjects = $db->table('mginbon_item_subjects as item_subjects')
                    ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
                    ->where('item_subjects.mginbon_item_id', $item->id)
                    ->whereIn('item_subjects.id', array_unique($validated['subject_ids']))
                    ->orderBy('subjects.sort_order')->get(['item_subjects.id', 'subjects.name']);
                abort_unless($subjects->count() === count(array_unique($validated['subject_ids'])), 422, '教科の選択が不正です。');

                $tasks = $db->table('mginbon_stage_tasks')->where('mginbon_item_id', $item->id)
                    ->where('mginbon_stage_definition_id', $stage->id)
                    ->whereIn('mginbon_item_subject_id', $subjects->pluck('id'))
                    ->lockForUpdate()->get();
                abort_unless($tasks->count() === $subjects->count(), 422, '工程データが不足しています。');
                abort_if($tasks->contains(fn ($task) => $task->project_job_assignment_id || $task->status !== 'not_started'),
                    422, '選択した作業のいずれかはすでに登録済みです。');

                $plannedPackages = $db->table('mginbon_work_package_tasks as links')
                    ->join('mginbon_work_packages as packages', 'packages.id', '=', 'links.mginbon_work_package_id')
                    ->whereIn('links.mginbon_stage_task_id', $tasks->pluck('id'))
                    ->where('packages.status', 'planned')
                    ->get(['packages.id', 'packages.user_id', 'packages.subcontractor_id'])->unique('id');
                $user = $request->user();
                $hasDifferentPlannedActor = $plannedActorPolicy
                    ->requiresReplacementConfirmation($plannedPackages, (int) $user->id);
                if ($hasDifferentPlannedActor && ! ($validated['replace_planned'] ?? false)) {
                    abort(409, '別の仮担当者が設定されています。あなたを正式担当者として登録しますか？');
                }

                $stageName = $this->userStageName($stage->code, $stage->name);
                $title = "銀本 {$item->display_name} {$item->media_name}（{$stageName}）";
                $detail = '対象教科: '.$subjects->pluck('name')->implode('・');
                $assignment = ProjectJobAssignment::create([
                    'project_job_id' => $projectJob->id,
                    'user_id' => $user->id,
                    'sender_id' => $user->id,
                    'title' => $title,
                    'detail' => $detail,
                    'assigned' => true,
                    'accepted' => true,
                    'company_id' => $user->company_id,
                    'department_id' => $user->department_id,
                ]);
                $packageId = $db->table('mginbon_work_packages')->insertGetId([
                    'mginbon_project_id' => $project->id,
                    'mginbon_item_id' => $item->id,
                    'mginbon_stage_definition_id' => $stage->id,
                    'user_id' => $user->id,
                    'project_job_assignment_id' => $assignment->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'created_by' => $user->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $plannedPackageIds = $plannedPackages->pluck('id');
                $db->table('mginbon_work_package_tasks')->whereIn('mginbon_stage_task_id', $tasks->pluck('id'))
                    ->whereIn('mginbon_work_package_id', $plannedPackageIds)->delete();
                foreach ($plannedPackageIds as $plannedPackageId) {
                    if (! $db->table('mginbon_work_package_tasks')->where('mginbon_work_package_id', $plannedPackageId)->exists()) {
                        $db->table('mginbon_work_packages')->where('id', $plannedPackageId)
                            ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
                    }
                }
                foreach ($tasks as $task) {
                    $db->table('mginbon_work_package_tasks')->insert([
                        'mginbon_work_package_id' => $packageId,
                        'mginbon_stage_task_id' => $task->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $participant = $db->table('mginbon_stage_task_participants')
                        ->where('mginbon_stage_task_id', $task->id)->where('role_type', $stage->activity_type)->first();
                    $participantValues = [
                        'execution_type' => 'internal', 'user_id' => $user->id, 'subcontractor_id' => null,
                        'resolution_status' => 'resolved', 'updated_at' => now(),
                    ];
                    $participant
                        ? $db->table('mginbon_stage_task_participants')->where('id', $participant->id)->update($participantValues)
                        : $db->table('mginbon_stage_task_participants')->insert([
                        'mginbon_stage_task_id' => $task->id,
                        'role_type' => $stage->activity_type,
                        ...$participantValues,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $db->table('mginbon_stage_tasks')->whereIn('id', $tasks->pluck('id'))->update([
                    'status' => 'assigned',
                    'project_job_assignment_id' => $assignment->id,
                    'updated_at' => now(),
                ]);
                $db->table('mginbon_items')->where('id', $item->id)->update(['updated_at' => now()]);
                $db->table('mginbon_change_logs')->insert([
                    'mginbon_project_id' => $project->id, 'mginbon_item_id' => $item->id,
                    'changed_by' => $user->id, 'field_path' => 'work_package.registered_actor',
                    'old_value' => json_encode($plannedPackages->map(fn ($package) => [
                        'user_id' => $package->user_id, 'subcontractor_id' => $package->subcontractor_id,
                    ])->values()->all(), JSON_UNESCAPED_UNICODE),
                    'new_value' => json_encode(['user_id' => $user->id, 'assignment_id' => $assignment->id,
                        'stage' => $stage->code, 'subject_ids' => $subjects->pluck('id')->all()], JSON_UNESCAPED_UNICODE),
                    'source' => 'user_registration', 'created_at' => now(), 'updated_at' => now(),
                ]);

                return ['assignment_id' => $assignment->id, 'title' => $title];
            });
        } catch (Throwable $exception) {
            if ($assignment) {
                $assignment->delete();
            }
            throw $exception;
        }

        return response()->json($result);
    }

    private function authorizeProject(Request $request, ProjectJob $projectJob): void
    {
        $user = $request->user();
        $hasAccess = ProjectTeamMember::query()->where('project_job_id', $projectJob->id)
            ->where('user_id', $user->id)->exists()
            || ProjectJobAssignment::query()->where('project_job_id', $projectJob->id)
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('sender_id', $user->id))->exists()
            || $projectJob->user_id === $user->id
            || $projectJob->coordinators()->where('users.id', $user->id)->exists()
            || in_array($user->user_role, ['superadmin', 'admin'], true);
        abort_unless($hasAccess, 403);
    }

    private function userStageName(string $code, string $name): string
    {
        // FileMaker由来の正式名は変えず、一般ユーザーの選択画面だけを簡潔にする。
        return $code === 'drawing' ? '作図' : $name;
    }
}
