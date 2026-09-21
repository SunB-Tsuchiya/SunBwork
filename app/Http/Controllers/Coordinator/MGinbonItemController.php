<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonItem;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonItemController extends Controller
{
    public function show(Request $request, MGinbonItem $item): Response
    {
        $data = $this->itemData($item->id);
        abort_unless($data, 404);

        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id') : $request->user()?->company_id;
        $globalSuperAdmin = $request->user()?->user_role === 'superadmin' && $companyId === null;

        return Inertia::render('Coordinator/MGinbon/ItemShow', [
            'item' => $data,
            'stageDefinitions' => DB::connection('mginbon')->table('mginbon_stage_definitions as stages')
                ->join('mginbon_production_units as units', 'units.mginbon_project_id', '=', 'stages.mginbon_project_id')
                ->join('mginbon_items as items', 'items.mginbon_production_unit_id', '=', 'units.id')
                ->where('items.id', $item->id)->where('stages.is_active', true)
                ->orderBy('stages.sort_order')->get(['stages.id', 'stages.code', 'stages.name', 'stages.activity_type']),
            'users' => User::query()->when($globalSuperAdmin, fn ($q) => $q->whereRaw('1 = 0'))
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->ordered()->get(['id', 'name']),
            'subcontractors' => Subcontractor::query()->when($globalSuperAdmin, fn ($q) => $q->whereRaw('1 = 0'))
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('name')->get(['id', 'name']),
            'history' => DB::connection('mginbon')->table('mginbon_change_logs')
                ->where('mginbon_item_id', $item->id)->latest()->limit(30)->get()
                ->map(fn ($row) => [
                    'id' => $row->id, 'field_path' => $row->field_path,
                    'old_value' => json_decode($row->old_value, true),
                    'new_value' => json_decode($row->new_value, true),
                    'changed_by' => $row->changed_by, 'created_at' => $row->created_at,
                ]),
        ]);
    }

    public function update(Request $request, MGinbonItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'updated_at' => ['required', 'string'],
            'display_name' => ['required', 'string', 'max:300'],
            'school_category' => ['nullable', 'string', 'max:50'],
            'n_category' => ['nullable', 'string', 'max:100'],
            'publication_status' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:5000'],
            'review_status' => ['required', Rule::in(['draft', 'review_required', 'confirmed'])],
            'shared_dates' => ['array'],
            'shared_dates.*' => ['nullable', 'date_format:Y-m-d'],
            'subjects' => ['array'],
            'subjects.*.id' => ['required', 'integer'],
            'subjects.*.dates' => ['array'],
            'subjects.*.dates.*' => ['nullable', 'date_format:Y-m-d'],
            'subjects.*.measurements' => ['array'],
            'subjects.*.measurements.*' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        $connection = DB::connection('mginbon');
        $connection->transaction(function () use ($request, $item, $validated, $connection) {
            $lockedItem = $connection->table('mginbon_items')->where('id', $item->id)->lockForUpdate()->first();
            if (! $lockedItem || (string) $lockedItem->updated_at !== $validated['updated_at']) {
                throw ValidationException::withMessages([
                    'updated_at' => 'ほかの利用者が更新しました。画面を再読み込みして確認してください。',
                ]);
            }
            $unit = $connection->table('mginbon_production_units')->where('id', $lockedItem->mginbon_production_unit_id)->first();
            $projectId = $unit->mginbon_project_id;

            $this->updateWithLogs($connection, $projectId, $item->id, null, 'mginbon_items', $item->id, [
                'publication_status' => $validated['publication_status'] ?? null,
                'note' => $validated['note'] ?? null,
                'review_status' => $validated['review_status'],
            ], 'item', $request->user()?->id);
            $this->updateWithLogs($connection, $projectId, $item->id, null, 'mginbon_production_units', $unit->id, [
                'display_name' => $validated['display_name'],
                'school_category' => $validated['school_category'] ?? null,
                'n_category' => $validated['n_category'] ?? null,
            ], 'unit', $request->user()?->id);

            $validSubjectIds = $connection->table('mginbon_item_subjects')->where('mginbon_item_id', $item->id)->pluck('id')->all();
            $this->syncMilestones($connection, $projectId, $item->id, null, $validated['shared_dates'] ?? [], 'shared_dates', $request->user()?->id);
            foreach ($validated['subjects'] ?? [] as $subject) {
                abort_unless(in_array($subject['id'], $validSubjectIds, true), 422);
                $this->syncMilestones($connection, $projectId, $item->id, $subject['id'], $subject['dates'] ?? [], 'subjects.'.$subject['id'].'.dates', $request->user()?->id);
                foreach ($subject['measurements'] ?? [] as $key => $quantity) {
                    [$workType, $executionType] = explode(':', $key, 2);
                    $measurement = $connection->table('mginbon_work_measurements')
                        ->where('mginbon_item_subject_id', $subject['id'])->where('work_type', $workType)
                        ->where('execution_type', $executionType)->first();
                    if ($measurement && (int) $measurement->quantity !== (int) $quantity) {
                        $connection->table('mginbon_work_measurements')->where('id', $measurement->id)->update(['quantity' => $quantity, 'updated_at' => now()]);
                        $this->log($connection, $projectId, $item->id, $subject['id'], "subjects.{$subject['id']}.measurements.{$key}", (int) $measurement->quantity, (int) $quantity, $request->user()?->id);
                    }
                }
            }
            $connection->table('mginbon_items')->where('id', $item->id)->update(['updated_at' => now()]);
        });

        return back()->with('success', '銀本進行データを更新しました。');
    }

    public function updateIntakeCheck(Request $request, MGinbonItem $item): JsonResponse
    {
        $validated = $request->validate([
            'updated_at' => ['required', 'string'],
            'subjects' => ['required', 'array', 'min:1', 'max:4'],
            'subjects.*.id' => ['required', 'integer'],
            'subjects.*.measurements' => ['required', 'array'],
            'subjects.*.measurements.scan:internal' => ['required', 'integer', 'min:0', 'max:999999'],
            'subjects.*.measurements.scan:subcontracted' => ['required', 'integer', 'min:0', 'max:999999'],
            'subjects.*.measurements.drawing:internal' => ['required', 'integer', 'min:0', 'max:999999'],
            'subjects.*.measurements.drawing:subcontracted' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);

        $connection = DB::connection('mginbon');
        $updatedAt = $connection->transaction(function () use ($request, $item, $validated, $connection) {
            $lockedItem = $connection->table('mginbon_items')->where('id', $item->id)->lockForUpdate()->first();
            if (! $lockedItem || (string) $lockedItem->updated_at !== $validated['updated_at']) {
                throw ValidationException::withMessages([
                    'updated_at' => 'ほかの利用者が更新しました。画面を再読み込みして確認してください。',
                ]);
            }
            $unit = $connection->table('mginbon_production_units')->where('id', $lockedItem->mginbon_production_unit_id)->first();
            $validSubjectIds = $connection->table('mginbon_item_subjects')
                ->where('mginbon_item_id', $item->id)->pluck('id')->all();

            foreach ($validated['subjects'] as $subject) {
                abort_unless(in_array($subject['id'], $validSubjectIds, true), 422);
                foreach ($subject['measurements'] as $key => $quantity) {
                    [$workType, $executionType] = explode(':', $key, 2);
                    abort_unless(in_array($workType, ['scan', 'drawing'], true)
                        && in_array($executionType, ['internal', 'subcontracted'], true), 422);
                    $measurement = $connection->table('mginbon_work_measurements')
                        ->where('mginbon_item_subject_id', $subject['id'])->where('work_type', $workType)
                        ->where('execution_type', $executionType)->first();
                    $old = (int) ($measurement?->quantity ?? 0);
                    if ($old === (int) $quantity) continue;
                    if ($measurement) {
                        $connection->table('mginbon_work_measurements')->where('id', $measurement->id)
                            ->update(['quantity' => $quantity, 'updated_at' => now()]);
                    } else {
                        $connection->table('mginbon_work_measurements')->insert([
                            'mginbon_item_subject_id' => $subject['id'], 'work_type' => $workType,
                            'execution_type' => $executionType, 'quantity' => $quantity, 'unit' => 'point',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    $this->log($connection, $unit->mginbon_project_id, $item->id, $subject['id'],
                        "subjects.{$subject['id']}.measurements.{$key}", $old, (int) $quantity, $request->user()?->id);
                }
            }
            $now = now();
            $connection->table('mginbon_items')->where('id', $item->id)->update(['updated_at' => $now]);
            return $now->format('Y-m-d H:i:s');
        });

        return response()->json(['message' => '入稿チェックを保存しました。', 'updated_at' => $updatedAt]);
    }

    private function itemData(int $itemId): ?array
    {
        $row = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->join('mginbon_projects as projects', 'projects.id', '=', 'units.mginbon_project_id')
            ->where('items.id', $itemId)->first([
                'items.*', 'units.mikuni_code', 'units.n_code', 'units.display_name', 'units.school_category',
                'units.n_category', 'media.name as media_name', 'projects.year',
            ]);
        if (! $row) return null;

        $subjects = DB::connection('mginbon')->table('mginbon_item_subjects as item_subjects')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->where('item_subjects.mginbon_item_id', $itemId)->orderBy('subjects.sort_order')
            ->get(['item_subjects.id', 'subjects.code', 'subjects.name']);
        $milestones = DB::connection('mginbon')->table('mginbon_milestones')->where('mginbon_item_id', $itemId)->get();
        $measurements = DB::connection('mginbon')->table('mginbon_work_measurements')->whereIn('mginbon_item_subject_id', $subjects->pluck('id'))->get();
        $tasks = DB::connection('mginbon')->table('mginbon_stage_tasks as tasks')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'tasks.mginbon_stage_definition_id')
            ->where('tasks.mginbon_item_id', $itemId)->orderBy('stages.sort_order')
            ->get([
                'tasks.id', 'tasks.mginbon_item_subject_id', 'tasks.status',
                'tasks.project_job_assignment_id', 'stages.name', 'stages.code',
            ]);
        $taskIds = $tasks->pluck('id');
        $participants = DB::connection('mginbon')->table('mginbon_stage_task_participants')
            ->whereIn('mginbon_stage_task_id', $taskIds)
            ->orderByDesc('id')->get()->groupBy('mginbon_stage_task_id');
        $packages = DB::connection('mginbon')->table('mginbon_work_package_tasks as package_tasks')
            ->join('mginbon_work_packages as packages', 'packages.id', '=', 'package_tasks.mginbon_work_package_id')
            ->whereIn('package_tasks.mginbon_stage_task_id', $taskIds)
            ->whereIn('packages.status', ['assigned', 'in_progress', 'completed'])
            ->orderByDesc('packages.id')
            ->get([
                'package_tasks.mginbon_stage_task_id', 'packages.user_id', 'packages.subcontractor_id',
                'packages.project_job_assignment_id', 'packages.status', 'packages.assigned_at', 'packages.completed_at',
            ])->groupBy('mginbon_stage_task_id')->map->first();
        $userNames = User::query()->whereIn('id', $packages->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');
        $subcontractorNames = Subcontractor::query()
            ->whereIn('id', $packages->pluck('subcontractor_id')->filter()->unique())
            ->pluck('name', 'id');

        return [
            ...((array) $row),
            'shared_dates' => $milestones->whereNull('mginbon_item_subject_id')->mapWithKeys(fn ($m) => [$m->code => $m->occurred_on]),
            'subjects' => $subjects->map(fn ($subject) => [
                ...((array) $subject),
                'dates' => $milestones->where('mginbon_item_subject_id', $subject->id)->mapWithKeys(fn ($m) => [$m->code => $m->occurred_on]),
                'measurements' => $measurements->where('mginbon_item_subject_id', $subject->id)->mapWithKeys(fn ($m) => ["{$m->work_type}:{$m->execution_type}" => (int) $m->quantity]),
                'stages' => $tasks->where('mginbon_item_subject_id', $subject->id)
                    ->map(function ($task) use ($participants, $packages, $userNames, $subcontractorNames) {
                        $package = $packages->get($task->id);
                        $participant = $participants->get($task->id, collect())->first();
                        $actor = $package?->user_id
                            ? ($userNames[$package->user_id] ?? '担当者')
                            : ($package?->subcontractor_id
                                ? ($subcontractorNames[$package->subcontractor_id] ?? '外注先')
                                : $participant?->legacy_value);

                        return [
                            'code' => $task->code,
                            'name' => $task->name,
                            'status' => $task->status,
                            'actor' => $actor,
                            'legacy_value' => $participant?->legacy_value,
                            'assignment_id' => $package?->project_job_assignment_id,
                            'assigned_at' => $package?->assigned_at,
                            'completed_at' => $package?->completed_at,
                        ];
                    })
                    ->filter(fn ($task) => $task['actor'] || $task['status'] !== 'not_started')
                    ->values(),
            ])->values(),
        ];
    }

    private function updateWithLogs($db, int $projectId, int $itemId, ?int $subjectId, string $table, int $id, array $values, string $prefix, ?int $userId): void
    {
        $current = $db->table($table)->where('id', $id)->first();
        $changes = [];
        foreach ($values as $field => $value) {
            if (($current->{$field} ?? null) != $value) {
                $changes[$field] = $value;
                $this->log($db, $projectId, $itemId, $subjectId, "{$prefix}.{$field}", $current->{$field} ?? null, $value, $userId);
            }
        }
        if ($changes) $db->table($table)->where('id', $id)->update([...$changes, 'updated_at' => now()]);
    }

    private function syncMilestones($db, int $projectId, int $itemId, ?int $subjectId, array $dates, string $prefix, ?int $userId): void
    {
        foreach ($dates as $code => $date) {
            $query = $db->table('mginbon_milestones')->where('mginbon_item_id', $itemId)->where('code', $code);
            $subjectId === null ? $query->whereNull('mginbon_item_subject_id') : $query->where('mginbon_item_subject_id', $subjectId);
            $current = $query->first();
            $old = $current?->occurred_on;
            if ($old === $date) continue;
            if ($date === null || $date === '') {
                if ($current) $query->delete();
            } elseif ($current) {
                $query->update(['occurred_on' => $date, 'source' => 'manual', 'updated_at' => now()]);
            } else {
                $db->table('mginbon_milestones')->insert(['mginbon_item_id' => $itemId, 'mginbon_item_subject_id' => $subjectId, 'code' => $code, 'occurred_on' => $date, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->log($db, $projectId, $itemId, $subjectId, "{$prefix}.{$code}", $old, $date, $userId);
        }
    }

    private function log($db, int $projectId, int $itemId, ?int $subjectId, string $path, mixed $old, mixed $new, ?int $userId): void
    {
        $db->table('mginbon_change_logs')->insert(['mginbon_project_id' => $projectId, 'mginbon_item_id' => $itemId, 'mginbon_item_subject_id' => $subjectId, 'changed_by' => $userId, 'field_path' => $path, 'old_value' => json_encode($old, JSON_UNESCAPED_UNICODE), 'new_value' => json_encode($new, JSON_UNESCAPED_UNICODE), 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
    }
}
