<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignmentByMyself;
use App\Models\ProjectJobAssignment;
use App\Models\JobAssignmentMessage;
use App\Models\Event;
use App\Models\ProgressCell;
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
            'assignments.*.source_assignment_id' => 'nullable|exists:project_job_assignments,id',
            'assignments.*.supersedes_assignment_id' => 'nullable|exists:project_job_assignments,id',
            'assignments.*.file_info' => 'nullable',
            'assignments.*._progress_sheet_id' => 'nullable|integer',
            'assignments.*._workflow_sheet_id' => 'nullable|integer',
            'assignments.*._row_id' => 'nullable|integer',
            'assignments.*._col_key' => 'nullable|string|max:64',
        ]);

        // validated payload received (debug logging removed)
        $user = $request->user();

        // (removed temporary debug logging)

        foreach ($data['assignments'] as $a) {
            DB::transaction(function () use ($projectJob, $a, $user) {
                // prefer explicit difficulty_id only
                $difficultyId = !empty($a['difficulty_id']) ? (int) $a['difficulty_id'] : null;

                $createPayload = [
                    'project_job_id' => $projectJob->id,
                    'source_assignment_id' => isset($a['source_assignment_id']) ? (int) $a['source_assignment_id'] : null,
                    'supersedes_assignment_id' => isset($a['supersedes_assignment_id']) ? (int) $a['supersedes_assignment_id'] : null,
                    'user_id' => $user ? $user->id : null,
                    'sender_id' => $user ? $user->id : null,
                    'title' => $a['title'],
                    'detail' => $a['detail'] ?? null,
                    'difficulty_id' => $difficultyId,
                    // scheduling fields removed: desired_start_date/start_time
                    // Prefer the user-selected work date (desired_start_date from the form)
                    // and store it into the `desired_end_date` column so UI's "作業日" is preserved.
                    'desired_end_date' => $a['desired_start_date'] ?? $a['desired_end_date'] ?? null,
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
                    'file_info' => !empty($a['file_info'])
                        ? (is_array($a['file_info']) ? $a['file_info'] : json_decode($a['file_info'], true))
                        : null,
                ];

                // legacy difficulty string column removed from payload

                // Create assignment: for user-created assignments prefer the
                // `ProjectJobAssignmentByMyself` model (alias for project_job_assignments
                // with a self_assigned global scope). sender_id = user_id が自己割当マーカー。
                if (class_exists(ProjectJobAssignmentByMyself::class)) {
                    // 自己割当ジョブは sender_id = user_id が必須。
                    // フォームから sender_id が送られない場合は認証ユーザーをデフォルトにする。
                    $createPayload['sender_id'] = $a['sender_id'] ?? ($user ? $user->id : null);
                    // 自己割当は本人が登録している時点で「確認済み＋セット済み」状態が正しい。
                    // (旧テーブル project_job_assignment_by_myself は統合済みのため
                    //  Schema::hasColumn チェックは不要。project_job_assignments に直接セットする)
                    $createPayload['assigned']     = true;
                    $createPayload['accepted']     = true;
                    $createPayload['scheduled']    = true;
                    $createPayload['read_at']      = now();
                    $createPayload['scheduled_at'] = now();

                    $assignment = ProjectJobAssignmentByMyself::create($createPayload);
                } else {
                    // Fallback to canonical table if the by_myself model/table isn't present
                    $createPayload['assigned']     = true;
                    $createPayload['accepted']     = true;
                    $createPayload['scheduled']    = true;
                    $createPayload['read_at']      = now();
                    $createPayload['scheduled_at'] = now();
                    $assignment = ProjectJobAssignment::create($createPayload);
                }

                // 進行表セルリンク: _row_id と _col_key が渡された場合（_progress_sheet_id は任意）
                if (!empty($a['_row_id']) && !empty($a['_col_key']) && empty($a['_workflow_sheet_id'])) {
                    try {
                        ProgressCell::updateOrCreate(
                            ['row_id' => (int)$a['_row_id'], 'col_key' => (string)$a['_col_key']],
                            ['assignment_id' => $assignment->id]
                        );
                    } catch (\Throwable $__eCellLink) {
                        \Illuminate\Support\Facades\Log::warning('Failed to link ProgressCell after user assignment', [
                            'error' => $__eCellLink->getMessage(),
                            'row_id' => $a['_row_id'],
                            'col_key' => $a['_col_key'],
                        ]);
                    }

                    // 進行表経由でジョブ登録 → リーダーへ通知
                    try {
                        if ($user) {
                            \App\Services\JobNotificationService::notifyProgressRegistered($user, $projectJob, $assignment);
                        }
                    } catch (\Throwable $__eNotify) {
                        \Illuminate\Support\Facades\Log::warning('Failed to send progress registration notification', [
                            'error' => $__eNotify->getMessage(),
                        ]);
                    }
                }

                // 管理シートセルリンク: _workflow_sheet_id / _row_id / _col_key が渡された場合
                if (!empty($a['_workflow_sheet_id']) && !empty($a['_row_id']) && !empty($a['_col_key'])) {
                    try {
                        \App\Models\WorkflowCell::updateOrCreate(
                            ['row_id' => (int) $a['_row_id'], 'stage_key' => (string) $a['_col_key']],
                            [
                                'assignment_id'    => $assignment->id,
                                'value_user_id'    => $assignment->user_id ?: null,
                                'assigned_user_id' => $assignment->user_id ?: null,
                            ]
                        );
                    } catch (\Throwable $__eWfCellLink) {
                        \Illuminate\Support\Facades\Log::warning('Failed to link WorkflowCell after user assignment', [
                            'error' => $__eWfCellLink->getMessage(),
                            'row_id' => $a['_row_id'],
                            'col_key' => $a['_col_key'],
                        ]);
                    }
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

                                // start_time がなければ 00:00 をデフォルトにしてイベントを必ず作成
                                $startTimePart = $startTimePart ?: '00:00';
                                $eventStart = \Carbon\Carbon::parse($datePart . ' ' . $startTimePart);

                                if ($endTimePart) {
                                    $eventEnd = \Carbon\Carbon::parse($datePart . ' ' . $endTimePart);
                                }
                                // end が start 以下なら end を start + 1時間 に補正
                                if ($eventEnd && $eventEnd->lessThanOrEqualTo($eventStart)) {
                                    $eventEnd = $eventStart->copy()->addHour();
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

                        // ── 重複イベントの interruption_minutes 処理 ──────────────────
                        // 作成したイベントと時間が重複する既存イベントを探し、
                        // 長い方のイベントから重複分を差し引く（interruption_minutes に加算）
                        if ($eventStart && $eventEnd) {
                            try {
                                $newDuration = $eventEnd->diffInMinutes($eventStart, false); // 秒→負になる可能性に注意
                                $newDurationMins = abs((int)$eventEnd->diffInMinutes($eventStart));

                                $overlappingEvents = Event::where('user_id', $event->user_id)
                                    ->where('id', '!=', $event->id)
                                    ->where('starts_at', '<', $eventEnd->toDateTimeString())
                                    ->where('ends_at', '>', $eventStart->toDateTimeString())
                                    ->get();

                                foreach ($overlappingEvents as $existingEv) {
                                    $evStart = \Carbon\Carbon::parse($existingEv->starts_at);
                                    $evEnd   = \Carbon\Carbon::parse($existingEv->ends_at);
                                    $existingDurationMins = abs((int)$evEnd->diffInMinutes($evStart));

                                    $overlapStart = $eventStart->gt($evStart) ? $eventStart : $evStart;
                                    $overlapEnd   = $eventEnd->lt($evEnd) ? $eventEnd : $evEnd;
                                    $overlapMins  = max(0, (int)$overlapStart->diffInMinutes($overlapEnd, false));

                                    if ($overlapMins <= 0) continue;

                                    if ($newDurationMins >= $existingDurationMins) {
                                        // 新しいイベントの方が長い → 自分（新しいイベント）から差し引く
                                        $event->increment('interruption_minutes', $overlapMins);
                                    } else {
                                        // 既存イベントの方が長い → 既存イベントから差し引く
                                        $existingEv->increment('interruption_minutes', $overlapMins);
                                    }
                                }
                            } catch (\Throwable $__overlapE) {
                                \Illuminate\Support\Facades\Log::warning('ProjectJobAssignmentController: failed to process event overlap', ['error' => $__overlapE->getMessage()]);
                            }
                        }
                        // ──────────────────────────────────────────────────────────────
                    }
                } catch (\Throwable $__e) {
                    // ignore event creation errors to avoid failing the assignment creation
                    \Illuminate\Support\Facades\Log::warning('Failed to create Event for ProjectJobAssignmentByMyself', ['error' => $__e->getMessage()]);
                }
            });
        }

        // 管理シート経由なら管理シートへ、それ以外はカレンダーへ
        $workflowSheetId = null;
        foreach ($data['assignments'] as $a) {
            if (!empty($a['_workflow_sheet_id'])) {
                $workflowSheetId = (int) $a['_workflow_sheet_id'];
                break;
            }
        }
        if ($workflowSheetId) {
            try {
                return redirect()->route('coordinator.workflow_sheets.show', ['sheet' => $workflowSheetId]);
            } catch (\Throwable $__e) {
                // fall through to calendar
            }
        }
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
        // For user-edit flow: do not modify the canonical `project_job_assignments` row.
        // Instead, create or update a `ProjectJobAssignmentByMyself` record linked to the
        // canonical assignment and optionally update/create the Event linked to that by-myself record.

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'difficulty_id' => 'nullable|exists:difficulties,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'desired_start_date' => 'nullable|date',
            'desired_end_date' => 'nullable|date',
            'desired_time' => 'nullable|date_format:H:i',
            'start_time' => 'nullable|date_format:H:i',
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

        $user = $request->user();

        DB::transaction(function () use ($assignment, $data, $user) {
            // Find the by-myself record by the exact assignment id passed in the route.
            // Previously this used project_job_id + user_id which could match a *different*
            // (newer) record when the same "その他" project had multiple assignments, causing
            // the wrong record (and wrong linked event) to be updated.
            $by = ProjectJobAssignmentByMyself::where('id', $assignment->id)->first();
            // Fallback: if not found via strict id lookup, try the old project_job_id + user_id search
            if (!$by) {
                $by = ProjectJobAssignmentByMyself::where('project_job_id', $assignment->project_job_id)
                    ->where('user_id', $user ? $user->id : null)
                    ->where('id', $assignment->id)
                    ->first();
            }

            $payload = [
                'project_job_id' => $assignment->project_job_id ?? null,
                'user_id' => $user ? $user->id : null,
                'sender_id' => $user ? $user->id : null,
                'title' => $data['title'],
                'detail' => $data['detail'] ?? null,
                'difficulty_id' => $data['difficulty_id'] ?? null,
                'desired_end_date' => $data['desired_end_date'] ?? null,
                'desired_time' => $data['desired_time'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'work_item_type_id' => $data['work_item_type_id'] ?? null,
                'size_id' => $data['size_id'] ?? null,
                'stage_id' => $data['stage_id'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'company_id' => $data['company_id'] ?? $assignment->company_id ?? null,
                'department_id' => $data['department_id'] ?? $assignment->department_id ?? null,
                'amounts' => $data['amounts'] ?? $assignment->amounts ?? null,
                'amounts_unit' => $data['amounts_unit'] ?? $assignment->amounts_unit ?? null,
            ];

            if ($by) {
                $by->fill($payload);
                $by->save();
            } else {
                $by = ProjectJobAssignmentByMyself::create($payload);
            }

            // Update or create Event linked to the by-myself assignment id
            try {
                if (Schema::hasTable('events') && Schema::hasColumn('events', 'project_job_assignment_id')) {
                    $event = Event::where('project_job_assignment_id', $by->id)->first();
                    // Build description
                    $lines = [];
                    $lines[] = 'ジョブ名: ' . ($by->title ?? '');
                    $lines[] = 'クライアント: ' . ($assignment->projectJob && $assignment->projectJob->client ? ($assignment->projectJob->client->name ?? '-') : '-');
                    $lines[] = '難易度: ' . ($by->difficultyModel?->name ?? '-');
                    $lines[] = '見積時間: ' . ($by->estimated_hours ?? '-');
                    $lines[] = '詳細:';
                    $lines[] = $by->detail ?? '';

                    // assemble start/end:
                    // 作業日 (desired_start_date from request) を優先し、なければ existing event の date を使う
                    $eventStart = null;
                    $eventEnd = null;
                    try {
                        // 作業日: リクエストの desired_start_date 優先、次に既存イベントの日付
                        $datePart = $data['desired_start_date'] ?? null;
                        if (empty($datePart) && $event) {
                            $datePart = \Carbon\Carbon::parse($event->start)->toDateString();
                        }
                        if (empty($datePart)) {
                            $datePart = $by->desired_end_date ?? null;
                        }
                        if (!empty($datePart)) {
                            $startTimePart = $by->start_time ?? null;
                            $endTimePart = $by->desired_time ?? null;
                            if ($startTimePart) $eventStart = \Carbon\Carbon::parse($datePart . ' ' . $startTimePart);
                            if ($endTimePart)   $eventEnd   = \Carbon\Carbon::parse($datePart . ' ' . $endTimePart);
                        }
                    } catch (\Throwable $__pe) {
                    }

                    if ($event) {
                        $event->title = $by->title ?? $event->title;
                        $event->description = implode("\n", $lines);
                        if ($eventStart) {
                            $event->start = $eventStart->toDateTimeString();
                            if (Schema::hasColumn('events', 'starts_at')) $event->starts_at = $eventStart->toDateTimeString();
                        }
                        if ($eventEnd) {
                            $event->end = $eventEnd->toDateTimeString();
                            if (Schema::hasColumn('events', 'ends_at')) $event->ends_at = $eventEnd->toDateTimeString();
                        }
                        // 時間変更時は interruption_minutes をリセットしてから再計算
                        if (($eventStart || $eventEnd) && Schema::hasColumn('events', 'interruption_minutes')) {
                            $event->interruption_minutes = 0;
                        }
                        $event->save();
                    } else {
                        // create new event and link it to the by-myself assignment
                        $event = new Event();
                        $event->user_id = $user ? $user->id : null;
                        $event->title = $by->title ?? '割当予定';
                        $event->description = implode("\n", $lines);
                        if ($eventStart) {
                            $event->start = $eventStart->toDateTimeString();
                            if (Schema::hasColumn('events', 'starts_at')) $event->starts_at = $eventStart->toDateTimeString();
                        }
                        if ($eventEnd) {
                            $event->end = $eventEnd->toDateTimeString();
                            if (Schema::hasColumn('events', 'ends_at')) $event->ends_at = $eventEnd->toDateTimeString();
                        }
                        if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                            $event->project_job_assignment_id = $by->id;
                        }
                        $event->save();
                    }

                    // ── 重複イベントの interruption_minutes 処理 ──────────────────
                    // 保存後、時間が重複する他のイベントとの差し引きを再計算する
                    if ($event && $eventStart && $eventEnd) {
                        try {
                            $newDurationMins = abs((int)$eventEnd->diffInMinutes($eventStart));

                            $overlappingEvents = Event::where('user_id', $event->user_id)
                                ->where('id', '!=', $event->id)
                                ->where('starts_at', '<', $eventEnd->toDateTimeString())
                                ->where('ends_at', '>', $eventStart->toDateTimeString())
                                ->get();

                            foreach ($overlappingEvents as $existingEv) {
                                $evStart = \Carbon\Carbon::parse($existingEv->starts_at);
                                $evEnd   = \Carbon\Carbon::parse($existingEv->ends_at);
                                $existingDurationMins = abs((int)$evEnd->diffInMinutes($evStart));

                                $overlapStart = $eventStart->gt($evStart) ? $eventStart : $evStart;
                                $overlapEnd   = $eventEnd->lt($evEnd)    ? $eventEnd   : $evEnd;
                                $overlapMins  = max(0, (int)$overlapStart->diffInMinutes($overlapEnd, false));

                                if ($overlapMins <= 0) continue;

                                if ($newDurationMins >= $existingDurationMins) {
                                    // 新しいイベントの方が長い → 自分から差し引く
                                    $event->increment('interruption_minutes', $overlapMins);
                                } else {
                                    // 既存イベントの方が長い → 既存から差し引く
                                    $existingEv->increment('interruption_minutes', $overlapMins);
                                }
                            }
                        } catch (\Throwable $__overlapE) {
                            \Illuminate\Support\Facades\Log::warning('ProjectJobAssignmentController::update: failed to process event overlap', ['error' => $__overlapE->getMessage()]);
                        }
                    }
                    // ──────────────────────────────────────────────────────────────
                }
            } catch (\Throwable $__e) {
                \Illuminate\Support\Facades\Log::warning('Failed to update/create Event for ProjectJobAssignmentByMyself', ['error' => $__e->getMessage()]);
            }
        });

        // 保存後は紐づくイベントの show へリダイレクト（なければ back）
        try {
            if (Schema::hasTable('events') && Schema::hasColumn('events', 'project_job_assignment_id')) {
                $linkedEvent = Event::where('project_job_assignment_id', $assignment->id)->first();
                if ($linkedEvent) {
                    return redirect()->route('events.show', $linkedEvent->id)->with('success', 'ジョブを更新しました。');
                }
            }
        } catch (\Throwable $__e) {}

        return redirect()->back()->with('success', 'ジョブを更新しました。');
    }
}
