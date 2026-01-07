<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignmentByMyself;
use App\Models\ProjectJobAssignment;
use App\Models\Event;
use Illuminate\Support\Facades\Schema;

class ProjectJobAssignmentController extends Controller
{
    /**
     * Store a new assignment created by the authenticated user (no coordinator side-effects)
     */
    public function store(Request $request, ProjectJob $projectJob)
    {
        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.title' => 'required|string|max:255',
            'assignments.*.detail' => 'nullable|string',
            // allow either difficulty_id (preferred) or difficulty string
            'assignments.*.difficulty_id' => 'nullable|exists:difficulties,id',
            'assignments.*.difficulty' => 'required_without:assignments.*.difficulty_id|in:light,normal,heavy',
            'assignments.*.estimated_hours' => 'nullable|numeric|min:0',
            'assignments.*.desired_start_date' => 'nullable|date',
            'assignments.*.desired_end_date' => 'nullable|date',
            'assignments.*.desired_time' => 'nullable|date_format:H:i',
            'assignments.*.start_time' => 'nullable|date_format:H:i',
            // lookup fields
            'assignments.*.work_item_type_id' => 'nullable|exists:work_item_types,id',
            'assignments.*.size_id' => 'nullable|exists:sizes,id',
            'assignments.*.status_id' => 'nullable|exists:statuses,id',
            'assignments.*.company_id' => 'nullable|exists:companies,id',
            'assignments.*.department_id' => 'nullable|exists:departments,id',
            'assignments.*.stage_id' => 'nullable|exists:stages,id',
            'assignments.*.amounts' => 'nullable|integer|min:0',
            'assignments.*.amounts_unit' => 'nullable|string|in:page,file',
            'assignments.*.sender_id' => 'nullable|exists:users,id',
        ]);

        // validated payload received (debug logging removed)
        $user = $request->user();

        foreach ($data['assignments'] as $a) {
            // basic logical validations
            if (!empty($a['desired_start_date']) && !empty($a['desired_end_date'])) {
                if ($a['desired_end_date'] < $a['desired_start_date']) {
                    return back()->withErrors(['assignments' => '終了希望日は割当希望日より前にできません。'])->withInput();
                }
            }

            DB::transaction(function () use ($projectJob, $a, $user) {
                // resolve difficulty id - prefer explicit difficulty_id
                $difficultyId = null;
                if (!empty($a['difficulty_id'])) {
                    $difficultyId = (int) $a['difficulty_id'];
                } elseif (!empty($a['difficulty'])) {
                    // if difficulty is numeric, treat as id; otherwise try to find by slug/name
                    if (is_numeric($a['difficulty'])) {
                        $difficultyId = (int) $a['difficulty'];
                    } else {
                        $q = \App\Models\Difficulty::query();
                        try {
                            if (Schema::hasColumn('difficulties', 'slug')) {
                                $q->where(function ($q2) use ($a) {
                                    $q2->where('slug', $a['difficulty'])->orWhere('name', $a['difficulty']);
                                });
                            } else {
                                $q->where('name', $a['difficulty']);
                            }
                        } catch (\Throwable $__e) {
                            $q->where('name', $a['difficulty']);
                        }
                        $d = $q->first();
                        if ($d) $difficultyId = $d->id;
                    }
                }

                $createPayload = [
                    'project_job_id' => $projectJob->id,
                    'user_id' => $user ? $user->id : null,
                    'sender_id' => $user ? $user->id : null,
                    'title' => $a['title'],
                    'detail' => $a['detail'] ?? null,
                    'difficulty_id' => $difficultyId,
                    'desired_start_date' => $a['desired_start_date'] ?? null,
                    'desired_end_date' => $a['desired_end_date'] ?? null,
                    'desired_time' => $a['desired_time'] ?? null,
                    'start_time' => $a['start_time'] ?? null,
                    'estimated_hours' => $a['estimated_hours'] ?? null,
                    'work_item_type_id' => $a['work_item_type_id'] ?? null,
                    'size_id' => $a['size_id'] ?? null,
                    'stage_id' => $a['stage_id'] ?? null,
                    'status_id' => $a['status_id'] ?? null,
                    'company_id' => $a['company_id'] ?? null,
                    'department_id' => $a['department_id'] ?? null,
                    'amounts' => $a['amounts'] ?? null,
                    'amounts_unit' => $a['amounts_unit'] ?? null,
                ];

                // include legacy string column only if table has it
                if (Schema::hasColumn('project_job_assignment_by_myself', 'difficulty')) {
                    $createPayload['difficulty'] = $a['difficulty'] ?? null;
                }

                // Create assignment in the canonical project_job_assignments table
                // Prefer ProjectJobAssignment when available so user-created assignments
                // appear in the main assignments list. Keep fallback to the legacy
                // "by_myself" table if needed.
                if (class_exists(ProjectJobAssignment::class)) {
                    // Ensure sender_id is null for user-created records as requested
                    $createPayload['sender_id'] = $a['sender_id'] ?? null;
                    $assignment = ProjectJobAssignment::create($createPayload);
                } else {
                    $assignment = ProjectJobAssignmentByMyself::create($createPayload);
                }

                // Create corresponding Event if events table supports linking
                try {
                    if (Schema::hasTable('events')) {
                        $start = null;
                        $end = null;
                        $startDate = $a['desired_start_date'] ?? null;
                        $endDate = $a['desired_end_date'] ?? $startDate;
                        // Prefer explicit start_time (separate selector) for event start; fall back to desired_time
                        $time = $a['start_time'] ?? $a['desired_time'] ?? null; // expected format HH:MM
                        if ($startDate && $time) {
                            $start = $startDate . ' ' . $time . ':00';
                            // Use end date and desired_time (end selector) if provided, else mirror start time
                            $endTime = $a['desired_time'] ?? $time;
                            $end = $endDate . ' ' . $endTime . ':00';
                        }

                        // Build description from assignment fields (similar to example provided)
                        $lines = [];
                        $lines[] = 'ジョブ名: ' . ($a['title'] ?? '');
                        // Try to include client/project if available
                        $lines[] = 'クライアント: ' . ($projectJob->client->name ?? ($a['client_name'] ?? '-'));
                        $lines[] = '難易度: ' . ($a['difficulty'] ?? '-');
                        $lines[] = '種別: ' . ($a['work_item_type_id'] ? ('id:' . $a['work_item_type_id']) : '-');
                        $lines[] = 'サイズ: ' . ($a['size_id'] ? ('id:' . $a['size_id']) : '-');
                        $lines[] = 'ステージ: ' . ($a['stage_id'] ? ('id:' . $a['stage_id']) : '-');
                        $lines[] = 'ステータス: ' . ($a['status_id'] ? ('id:' . $a['status_id']) : '-');
                        $lines[] = '見積時間: ' . ($a['estimated_hours'] ?? '-');
                        $lines[] = '確認済み: ' . (isset($a['confirmed']) && $a['confirmed'] ? 'はい' : 'いいえ');
                        $lines[] = 'プロジェクトジョブ詳細: ' . ($projectJob->detail ?? '-');
                        $lines[] = '割当ユーザーID: ' . ($a['user_id'] ?? '-');
                        $lines[] = '担当ユーザー: ' . ($user ? $user->name : '-');
                        $lines[] = '希望期間: ' . ($startDate ? ($startDate . ' ' . ($time ?? '') . ' 〜 ' . ($endDate ?? $startDate) . ' ' . ($a['desired_time'] ?? $time ?? '')) : '-');
                        $lines[] = '詳細:';
                        $lines[] = $a['detail'] ?? '';

                        $description = implode("\n", $lines);

                        $event = new Event();
                        $event->user_id = $user ? $user->id : null;
                        $event->title = $a['title'] ?? '割当予定';
                        $event->description = $description;
                        if ($start) $event->start = $start;
                        if ($end) $event->end = $end;
                        // link back to the created assignment if column exists
                        // Link event to whichever assignment table we created the record in
                        if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                            $event->project_job_assignment_id = $assignment->id;
                        } elseif (Schema::hasColumn('events', 'project_job_assignment_by_myself_id')) {
                            $event->project_job_assignment_by_myself_id = $assignment->id;
                        }
                        $event->save();
                    }
                } catch (\Throwable $__e) {
                    // ignore event creation errors to avoid failing the assignment creation
                    \Illuminate\Support\Facades\Log::warning('Failed to create Event for ProjectJobAssignmentByMyself', ['error' => $__e->getMessage()]);
                }
            });
        }

        // Redirect to calendar after creation
        try {
            return redirect()->route('calendar.index');
        } catch (\Exception $e) {
            return redirect()->route('user.assigned-projects.index');
        }
    }
}
