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
            'assignments.*.desired_start_date' => 'nullable|date',
            'assignments.*.start_time' => 'nullable|date_format:H:i',
            'assignments.*.title' => 'required|string|max:255',
            'assignments.*.detail' => 'nullable|string',
            // accept difficulty by id only (legacy string removed)
            'assignments.*.difficulty_id' => 'nullable|exists:difficulties,id',
            'assignments.*.estimated_hours' => 'nullable|numeric|min:0',
            'assignments.*.desired_end_date' => 'nullable|date',
            'assignments.*.desired_time' => 'nullable|date_format:H:i',
            // scheduling fields removed: desired_start_date/start_time
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

        // (removed temporary debug logging)

        foreach ($data['assignments'] as $a) {
            // basic logical validations
            if (!empty($a['desired_start_date']) && !empty($a['desired_end_date'])) {
                if ($a['desired_end_date'] < $a['desired_start_date']) {
                    return back()->withErrors(['assignments' => '終了希望日は割当希望日より前にできません。'])->withInput();
                }
            }

            DB::transaction(function () use ($projectJob, $a, $user) {
                // prefer explicit difficulty_id only
                $difficultyId = !empty($a['difficulty_id']) ? (int) $a['difficulty_id'] : null;

                $createPayload = [
                    'project_job_id' => $projectJob->id,
                    'user_id' => $user ? $user->id : null,
                    'sender_id' => $user ? $user->id : null,
                    'title' => $a['title'],
                    'detail' => $a['detail'] ?? null,
                    'difficulty_id' => $difficultyId,
                    // scheduling fields removed: desired_start_date/start_time
                    'desired_end_date' => $a['desired_end_date'] ?? null,
                    'desired_time' => $a['desired_time'] ?? null,
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

                // legacy difficulty string column removed from payload

                // Create assignment: for user-created assignments prefer the
                // `project_job_assignment_by_myself` table so users' own schedules
                // are stored separately from coordinator-created canonical rows.
                if (class_exists(ProjectJobAssignmentByMyself::class)) {
                    $createPayload['sender_id'] = $a['sender_id'] ?? null;
                    $assignment = ProjectJobAssignmentByMyself::create($createPayload);
                } else {
                    // Fallback to canonical table if the by_myself model/table isn't present
                    $assignment = ProjectJobAssignment::create($createPayload);
                }

                // Create corresponding Event if events table supports linking
                try {
                    if (Schema::hasTable('events')) {
                        // Build description from assignment fields
                        $lines = [];
                        $lines[] = 'ジョブ名: ' . ($a['title'] ?? '');
                        // Try to include client/project if available
                        $lines[] = 'クライアント: ' . ($projectJob->client->name ?? ($a['client_name'] ?? '-'));
                        // prefer difficulty name resolved from difficulty_id
                        $dname = '-';
                        if (!empty($difficultyId)) {
                            $dObj = \App\Models\Difficulty::find($difficultyId);
                            if ($dObj) $dname = $dObj->name;
                        }
                        $lines[] = '難易度: ' . $dname;
                        $lines[] = '種別: ' . ($a['work_item_type_id'] ? ('id:' . $a['work_item_type_id']) : '-');
                        $lines[] = 'サイズ: ' . ($a['size_id'] ? ('id:' . $a['size_id']) : '-');
                        $lines[] = 'ステージ: ' . ($a['stage_id'] ? ('id:' . $a['stage_id']) : '-');
                        $lines[] = 'ステータス: ' . ($a['status_id'] ? ('id:' . $a['status_id']) : '-');
                        $lines[] = '見積時間: ' . ($a['estimated_hours'] ?? '-');
                        $lines[] = '確認済み: ' . (isset($a['confirmed']) && $a['confirmed'] ? 'はい' : 'いいえ');
                        $lines[] = 'プロジェクトジョブ詳細: ' . ($projectJob->detail ?? '-');
                        $lines[] = '割当ユーザーID: ' . ($a['user_id'] ?? '-');
                        $lines[] = '担当ユーザー: ' . ($user ? $user->name : '-');

                        // If inline event editor provided start/end parts, assemble start/end
                        $eventStart = null;
                        $eventEnd = null;
                        try {
                            if (!empty($a['desired_start_date'])) {
                                $datePart = $a['desired_start_date'];

                                // Prefer explicit combined time strings first
                                $startTimePart = $a['start_time'] ?? null;
                                $endTimePart = $a['desired_time'] ?? null;

                                // Fallback to hour/min parts if combined strings are not provided
                                if (empty($startTimePart) && (isset($a['start_time_hour']) || isset($a['start_time_min']))) {
                                    $sh = isset($a['start_time_hour']) ? sprintf('%02d', $a['start_time_hour']) : '09';
                                    $sm = isset($a['start_time_min']) ? sprintf('%02d', $a['start_time_min']) : '00';
                                    $startTimePart = $sh . ':' . $sm;
                                }
                                if (empty($endTimePart) && (isset($a['desired_time_hour']) || isset($a['desired_time_min']))) {
                                    $eh = isset($a['desired_time_hour']) ? sprintf('%02d', $a['desired_time_hour']) : '10';
                                    $em = isset($a['desired_time_min']) ? sprintf('%02d', $a['desired_time_min']) : '00';
                                    $endTimePart = $eh . ':' . $em;
                                }

                                if ($startTimePart) {
                                    $eventStart = \Carbon\Carbon::parse($datePart . ' ' . $startTimePart);
                                }
                                if ($endTimePart) {
                                    $eventEnd = \Carbon\Carbon::parse($datePart . ' ' . $endTimePart);
                                }
                            }
                        } catch (\Throwable $__parseE) {
                            // ignore parse errors and leave start/end null
                        }

                        $lines[] = '希望日時: ' . (!empty($a['desired_start_date']) ? ($a['desired_start_date'] . ' ' . ($a['start_time'] ?? '') . ' - ' . ($a['desired_time'] ?? '')) : '-');
                        $lines[] = '詳細:';
                        $lines[] = $a['detail'] ?? '';

                        $description = implode("\n", $lines);

                        $event = new Event();
                        $event->user_id = $user ? $user->id : null;
                        $event->title = $a['title'] ?? '割当予定';
                        $event->description = $description;

                        // (removed temporary parsed-time debug logging)

                        if ($eventStart) {
                            $event->start = $eventStart->toDateTimeString();
                            if (Schema::hasColumn('events', 'starts_at')) {
                                $event->starts_at = $eventStart->toDateTimeString();
                            }
                        }
                        if ($eventEnd) {
                            $event->end = $eventEnd->toDateTimeString();
                            if (Schema::hasColumn('events', 'ends_at')) {
                                $event->ends_at = $eventEnd->toDateTimeString();
                            }
                        }
                        // If the events table has a date column, set it from start
                        if ($eventStart && Schema::hasColumn('events', 'date')) {
                            try {
                                $event->date = $eventStart->toDateString();
                            } catch (\Throwable $__eDate) {
                                // ignore
                            }
                        }

                        // link back to the created assignment if canonical column exists
                        if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                            $event->project_job_assignment_id = $assignment->id;
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

    /**
     * Update an existing assignment created by the authenticated user.
     * This will update the canonical project_job_assignments row and any
     * linked event (events.project_job_assignment_id) to keep title/description/start/end in sync.
     */
    public function update(Request $request, ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        // Debug: log authentication and assignment info to help diagnose 403
        try {
            \Illuminate\Support\Facades\Log::info('ProjectJobAssignment:update attempt', [
                'auth_id' => $request->user() ? $request->user()->id : null,
                'assignment_id' => $assignment->id,
                'assignment_user_id' => $assignment->user_id,
                'assignment_sender_id' => $assignment->sender_id,
                'session_id' => session()->getId(),
                'cookies' => array_keys($request->cookies->all()),
            ]);
        } catch (\Throwable $__e) {
            // ignore logging errors
        }

        $this->authorize('update', $assignment);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'difficulty_id' => 'nullable|exists:difficulties,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            // scheduling fields removed: desired_start_date/start_time
            'desired_end_date' => 'nullable|date',
            'desired_time' => 'nullable|date_format:H:i',
            'work_item_type_id' => 'nullable|exists:work_item_types,id',
            'size_id' => 'nullable|exists:sizes,id',
            'status_id' => 'nullable|exists:statuses,id',
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'stage_id' => 'nullable|exists:stages,id',
            'amounts' => 'nullable|integer|min:0',
            'amounts_unit' => 'nullable|string|in:page,file',
            'user_id' => 'nullable|exists:users,id',
        ]);

        DB::transaction(function () use ($assignment, $data) {
            // map fields
            $assignment->title = $data['title'];
            $assignment->detail = $data['detail'] ?? $assignment->detail;
            if (isset($data['difficulty_id'])) $assignment->difficulty_id = $data['difficulty_id'];
            if (isset($data['desired_end_date'])) $assignment->desired_end_date = $data['desired_end_date'];
            if (isset($data['desired_time'])) $assignment->desired_time = $data['desired_time'];
            if (isset($data['estimated_hours'])) $assignment->estimated_hours = $data['estimated_hours'];
            if (isset($data['work_item_type_id'])) $assignment->work_item_type_id = $data['work_item_type_id'];
            if (isset($data['size_id'])) $assignment->size_id = $data['size_id'];
            if (isset($data['stage_id'])) $assignment->stage_id = $data['stage_id'];
            if (isset($data['status_id'])) $assignment->status_id = $data['status_id'];
            if (isset($data['company_id'])) $assignment->company_id = $data['company_id'];
            if (isset($data['department_id'])) $assignment->department_id = $data['department_id'];
            if (isset($data['amounts'])) $assignment->amounts = $data['amounts'];
            if (isset($data['amounts_unit'])) $assignment->amounts_unit = $data['amounts_unit'];
            if (isset($data['user_id'])) $assignment->user_id = $data['user_id'];

            $assignment->save();

            // Update linked event if present
            try {
                if (Schema::hasTable('events') && Schema::hasColumn('events', 'project_job_assignment_id')) {
                    $event = Event::where('project_job_assignment_id', $assignment->id)->first();
                    if ($event) {
                        // rebuild event title/description and start/end if desired dates provided
                        $event->title = $assignment->title;
                        $lines = [];
                        $lines[] = 'ジョブ名: ' . ($assignment->title ?? '');
                        $lines[] = 'クライアント: ' . ($assignment->projectJob && $assignment->projectJob->client ? ($assignment->projectJob->client->name ?? '-') : '-');
                        $lines[] = '難易度: ' . ($assignment->difficultyModel?->name ?? '-');
                        $lines[] = '見積時間: ' . ($assignment->estimated_hours ?? '-');
                        $lines[] = '詳細:';
                        $lines[] = $assignment->detail ?? '';
                        $event->description = implode("\n", $lines);

                        // scheduling fields removed: do not update event start/end here
                        $event->save();
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }
        });

        return redirect()->route('calendar.index');
    }
}
