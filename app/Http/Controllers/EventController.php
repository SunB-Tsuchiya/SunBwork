<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Intervention\Image\ImageManager;
use Inertia\Inertia;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectJobAssignmentByMyself;
use App\Models\UserMonthlyBreak;
use App\Models\UserSetting;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Events\MessageCreated;
use App\Services\HtmlSanitizer;
use App\Models\Team;
use App\Models\Unit;
use App\Http\Controllers\Concerns\CalculatesEventTime;

class EventController extends Controller
{
    use CalculatesEventTime;

    // Reuse permission logic from DiaryInteractionController to determine which user ids
    // the current actor may inspect. Kept local to avoid cross-controller dependency.
    protected function buildPermittedUserIdsForActor($currentUser)
    {
        if (!$currentUser) return [];
        if (method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin()) {
            return User::pluck('id')->toArray();
        }

        $isAdmin = ($currentUser->user_role ?? '') === 'admin';
        $userIds = [];

        if ($isAdmin) {
            $companyId = $currentUser->company_id;
            $users = User::where('company_id', $companyId)->get();
            $userIds = $users->pluck('id')->toArray();
            return array_values(array_unique(array_filter($userIds)));
        }

        // リーダーとして所属するチーム（leader_id）
        $leaderTeams = Team::where('leader_id', $currentUser->id)
            ->whereIn('team_type', ['department', 'unit'])
            ->get();

        // サブリーダーとして所属するチーム
        $subLeaderTeamIds = DB::table('team_sub_leaders')
            ->where('user_id', $currentUser->id)
            ->pluck('team_id');
        $subLeaderTeams = Team::whereIn('id', $subLeaderTeamIds)
            ->whereIn('team_type', ['department', 'unit'])
            ->get();

        $allTeams = $leaderTeams->merge($subLeaderTeams)->unique('id');

        foreach ($allTeams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                $deptUsers = User::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->pluck('id')
                    ->toArray();
                $userIds = array_merge($userIds, $deptUsers);
            }

            if ($team->team_type === 'unit') {
                $unit = Unit::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->where('name', $team->name)
                    ->first();
                if ($unit) {
                    $members = $unit->members()->pluck('users.id')->toArray();
                    $userIds = array_merge($userIds, $members);
                }
            }
        }

        // 日報管理チームリーダーとして登録されているユーザーはそのチームメンバーのイベントを閲覧可能
        // （Coordinator/Clerk が diary_team_leaders に登録されている場合に対応）
        $diaryMemberIds = $currentUser->diaryManagerMemberIds();
        if (!empty($diaryMemberIds)) {
            $userIds = array_merge($userIds, $diaryMemberIds);
        }

        return array_values(array_unique(array_filter($userIds)));
    }
    /**
     * カレンダーからのリサイズ用: 時間のみバリデート・更新
     */
    public function update_from_calendar(Request $request, $id)
    {
        $event = Event::where('user_id', Auth::id())->findOrFail($id);

        // 会議室予約に紐づく予定はこの経路では扱わない（ScheduleEventController と同様のガード）
        if ($event->room_reservation_id) {
            abort(422, 'この予定は会議室予約に紐づいています。会議室予約から編集してください');
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'startHour' => ['required', 'regex:/^\\d{2}$/'],
            'startMinute' => ['required', 'regex:/^\\d{2}$/'],
            'endHour' => ['required', 'regex:/^\\d{2}$/'],
            'endMinute' => ['required', 'regex:/^\\d{2}$/'],
        ]);

        // 日付と時刻を結合（JST）
        $start = $validated['date'] . ' ' . $validated['startHour'] . ':' . $validated['startMinute'] . ':00';
        $end   = $validated['date'] . ' ' . $validated['endHour']   . ':' . $validated['endMinute']   . ':00';

        // 旧時刻を記録（recalc で旧重複範囲の解除に使用）
        $oldStart = $event->getRawOriginal('starts_at');
        $oldEnd   = $event->getRawOriginal('ends_at');

        // setStart/EndAttribute が $start/$end の JST 文字列を attributes['starts_at'/'ends_at'] に
        // そのまま格納する（EventController::store() と同じ方式）。
        // UTC 変換を行うと events テーブル全体で格納方式が不整合になるため除去する。
        $event->start = $start;
        $event->end   = $end;
        $event->save();

        // proof_schedule 自動連動
        $this->syncProofScheduleFromEvent($event);

        // Q-01: 重複分の再計算（旧時間帯の解除 + 新時間帯の計算）
        $this->recalcInterruptionMinutes($event, $oldStart, $oldEnd);

        return response()->json(['message' => 'Event time updated successfully.']);
    }


    // ユーザーの予定一覧取得（カレンダー表示用）
    public function index(Request $request)
    {
        $date = $request->query('date');
        $requestedUserId = $request->query('user_id');
        $jobFilter = $request->query('job');

        // Default: only current user's events
        $baseUserId = Auth::id();

        // If a user_id was requested, validate the caller has permission to view that user's events
        if ($requestedUserId) {
            // compute permitted user ids for current actor (reuse DiaryInteraction logic locally)
            $permitted = $this->buildPermittedUserIdsForActor(Auth::user());
            // allow if requesting own events or the requested user is in permitted list
            if (intval($requestedUserId) === intval(Auth::id()) || in_array(intval($requestedUserId), $permitted) || (Auth::user()->user_role ?? '') === 'admin') {
                $baseUserId = intval($requestedUserId);
            } else {
                abort(403, 'このユーザーの予定を表示する権限がありません');
            }
        }

        $query = Event::where('user_id', $baseUserId);
        // If a specific job/assignment filter provided, restrict to events linked to that assignment
        if ($jobFilter && Schema::hasColumn('events', 'project_job_assignment_id')) {
            $query->where('project_job_assignment_id', intval($jobFilter));
        }
        if ($date) {
            // Interpret the incoming date (YYYY-MM-DD) in the application's timezone (Asia/Tokyo).
            // Events are stored in the app timezone, so filter using only the local day range.
            // Using orWhereBetween with a UTC range caused events from adjacent days to bleed
            // through (e.g. prev-day events at 15:00+ JST matched the UTC window for the next day).
            try {
                $tz = config('app.timezone') ?: 'UTC';
                $startOfDay = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
                $endOfDay = Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay();
                $localStartStr = $startOfDay->toDateTimeString();
                $localEndStr = $endOfDay->toDateTimeString();
            } catch (\Exception $e) {
                // fallback to naive whereDate if parsing fails
                if (Schema::hasColumn('events', 'starts_at')) {
                    $query->whereDate('starts_at', $date);
                } else {
                    $query->whereDate('start', $date);
                }
                $localStartStr = null;
                $localEndStr = null;
            }

            if (isset($localStartStr) && isset($localEndStr)) {
                if (Schema::hasColumn('events', 'starts_at')) {
                    $query->whereBetween('starts_at', [$localStartStr, $localEndStr]);
                } else {
                    $query->whereBetween('start', [$localStartStr, $localEndStr]);
                }
            }
        }
        $events = $query->with('projectJobAssignment:id,job_type')->get();

        // For JSON clients (axios in the diary interactions view) enrich events
        // with the same job/progress/self-assigned metadata and color mapping
        // used by the calendar so the TimelineDiary can render matching colors.
        if ($request->wantsJson()) {
            try {
                $assignmentIds = $events->pluck('project_job_assignment_id')->filter()->unique()->values()->all();

                // detect progress-linked assignments
                $progressAssignmentIds = [];
                if (!empty($assignmentIds)) {
                    try {
                        $progressAssignmentIds = \App\Models\ProgressCell::whereIn('assignment_id', $assignmentIds)->pluck('assignment_id')->map(fn($v) => (int)$v)->all();
                    } catch (\Throwable $__ex) {
                        \Illuminate\Support\Facades\Log::warning('EventController: progressAssignmentIds lookup failed', ['error' => $__ex->getMessage()]);
                    }
                }

                // load senders from canonical and by_myself assignments to detect self-assigned
                $assignmentSenders = [];
                // coordinator_assignment_id が設定されている assignment は、見た目は自己割当でも
                // コーディネーターに依頼されたジョブ（校正スケジュール等）なのでグリーン表示にする
                $assignmentCoordinatorIds = []; // assignment_id => coordinator_assignment_id|null
                if (!empty($assignmentIds)) {
                    try {
                        $rows = \App\Models\ProjectJobAssignment::whereIn('id', $assignmentIds)
                            ->get(['id', 'sender_id', 'coordinator_assignment_id']);
                        $senders    = $rows->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                        $coordIds   = $rows->pluck('coordinator_assignment_id', 'id')->all();
                        $assignmentCoordinatorIds = $coordIds;
                    } catch (\Throwable $__ex) {
                        $senders = [];
                        \Illuminate\Support\Facades\Log::warning('EventController: assignment senders lookup failed', ['error' => $__ex->getMessage()]);
                    }
                    try {
                        $bySenders = [];
                        if (class_exists(\App\Models\ProjectJobAssignmentByMyself::class)) {
                            $bySenders = \App\Models\ProjectJobAssignmentByMyself::whereIn('id', $assignmentIds)->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                        }
                    } catch (\Throwable $__ex) {
                        $bySenders = [];
                        \Illuminate\Support\Facades\Log::warning('EventController: by_myself senders lookup failed', ['error' => $__ex->getMessage()]);
                    }

                    // preserve keys and let by_myself override canonical
                    if (is_array($senders)) {
                        foreach ($senders as $k => $v) $assignmentSenders[$k] = $v;
                    }
                    if (is_array($bySenders)) {
                        foreach ($bySenders as $k => $v) $assignmentSenders[$k] = $v;
                    }
                    foreach ($assignmentSenders as $k => $v) {
                        $assignmentSenders[$k] = $v === null ? null : (int)$v;
                    }
                }

                // 休憩設定をユーザー分まとめて取得（日別設定 → グローバル設定の優先順）
                $userLunchSettingForIndex = null;
                try {
                    $userLunchSettingForIndex = \App\Models\UserSetting::where('user_id', $baseUserId)->first();
                } catch (\Throwable $_) {}
                $lunchBreakCache = []; // date => breakInfo|null

                $mapped = $events->map(function ($e) use ($progressAssignmentIds, $assignmentSenders, $assignmentCoordinatorIds, $baseUserId, $userLunchSettingForIndex, &$lunchBreakCache) {
                    $arr = $e->toArray();
                    $pjId = $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null);
                    $hasProgress = $pjId ? in_array((int)$pjId, $progressAssignmentIds, true) : false;
                    $isSelf = false;
                    if ($pjId && isset($assignmentSenders[$pjId])) {
                        $sender = $assignmentSenders[$pjId];
                        $isSelf = $sender !== null && intval($sender) === intval($baseUserId);
                        // coordinator_assignment_id が設定されている場合は依頼されたジョブ扱い
                        // （校正スケジュールから自動作成された pja101 等）
                        if ($isSelf && !empty($assignmentCoordinatorIds[$pjId])) {
                            $isSelf = false;
                        }
                    }

                    // color mapping: progress -> purple, proof -> pink, self-assigned -> indigo, default -> green
                    $color = $arr['color'] ?? ($e->color ?? null);
                    if (!$color) {
                        $isProofAssignment = $pjId && !empty($assignmentCoordinatorIds[$pjId]);
                        if ($hasProgress) $color = '#7C3AED';
                        elseif ($isProofAssignment) $color = '#DB2777';
                        elseif ($isSelf) $color = '#4F46E5';
                        else $color = '#059669';
                    }

                    $arr['color'] = $color;
                    $arr['extendedProps'] = array_merge($arr['extendedProps'] ?? [], [
                        'project_job_assignment_id' => $pjId,
                        'has_progress_cell' => $hasProgress,
                        'is_self_assigned' => $isSelf,
                    ]);

                    // Q-04+Q-07: ランチ休憩の重複分を計算して付与
                    $lunchOverlapMinutes = 0;
                    try {
                        $evStart = $this->resolveJstCarbon($e, 'starts_at');
                        $evEnd   = $this->resolveJstCarbon($e, 'ends_at');
                        if ($evStart && $evEnd) {
                            $lunchOverlapMinutes = $this->computeLunchMinutes($evStart, $evEnd, (int)$baseUserId, $lunchBreakCache);
                        }
                    } catch (\Throwable $_) {}
                    $arr['lunch_overlap_minutes'] = $lunchOverlapMinutes;

                    return $arr;
                })->values();

                return response()->json($mapped);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('EventController: failed to enrich events for JSON response', ['error' => $e->getMessage()]);
                return response()->json($events);
            }
        }

        // For Inertia page rendering, enrich events with the same job/progress/self-assigned
        // metadata so the Calendar component can pick correct colors without an extra JSON call.
        $mappedForInertia = $events;
        try {
            $assignmentIds = $events->pluck('project_job_assignment_id')->filter()->unique()->values()->all();

            $progressAssignmentIds = [];
            if (!empty($assignmentIds)) {
                try {
                    $progressAssignmentIds = \App\Models\ProgressCell::whereIn('assignment_id', $assignmentIds)->pluck('assignment_id')->map(fn($v) => (int)$v)->all();
                } catch (\Throwable $__ex) {
                    \Illuminate\Support\Facades\Log::warning('EventController: progressAssignmentIds lookup failed (inertia)', ['error' => $__ex->getMessage()]);
                }
            }

            $assignmentSenders = [];
            $assignmentCoordinatorIds = []; // assignment_id => coordinator_assignment_id|null
            if (!empty($assignmentIds)) {
                try {
                    $rows = \App\Models\ProjectJobAssignment::whereIn('id', $assignmentIds)
                        ->get(['id', 'sender_id', 'coordinator_assignment_id']);
                    $senders  = $rows->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                    $assignmentCoordinatorIds = $rows->pluck('coordinator_assignment_id', 'id')->all();
                } catch (\Throwable $__ex) {
                    $senders = [];
                    \Illuminate\Support\Facades\Log::warning('EventController: assignment senders lookup failed (inertia)', ['error' => $__ex->getMessage()]);
                }
                try {
                    $bySenders = [];
                    if (class_exists(\App\Models\ProjectJobAssignmentByMyself::class)) {
                        $bySenders = \App\Models\ProjectJobAssignmentByMyself::whereIn('id', $assignmentIds)->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                    }
                } catch (\Throwable $__ex) {
                    $bySenders = [];
                    \Illuminate\Support\Facades\Log::warning('EventController: by_myself senders lookup failed (inertia)', ['error' => $__ex->getMessage()]);
                }

                if (is_array($senders)) {
                    foreach ($senders as $k => $v) $assignmentSenders[$k] = $v;
                }
                if (is_array($bySenders)) {
                    foreach ($bySenders as $k => $v) $assignmentSenders[$k] = $v;
                }
                foreach ($assignmentSenders as $k => $v) {
                    $assignmentSenders[$k] = $v === null ? null : (int)$v;
                }
            }

            $mappedForInertia = $events->map(function ($e) use ($progressAssignmentIds, $assignmentSenders, $assignmentCoordinatorIds, $baseUserId) {
                $arr = $e->toArray();
                $pjId = $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null);
                $hasProgress = $pjId ? in_array((int)$pjId, $progressAssignmentIds, true) : false;
                $isSelf = false;
                if ($pjId && isset($assignmentSenders[$pjId])) {
                    $sender = $assignmentSenders[$pjId];
                    $isSelf = $sender !== null && intval($sender) === intval($baseUserId);
                    // coordinator_assignment_id が設定されている場合は依頼されたジョブ扱い
                    if ($isSelf && !empty($assignmentCoordinatorIds[$pjId])) {
                        $isSelf = false;
                    }
                }

                $arr['extendedProps'] = array_merge($arr['extendedProps'] ?? [], [
                    'project_job_assignment_id' => $pjId,
                    'has_progress_cell' => $hasProgress,
                    'is_self_assigned' => $isSelf,
                ]);

                // keep color if present, otherwise leave to frontend mapping
                if (!isset($arr['color']) && !isset($arr['color'])) {
                    // no-op; frontend will choose based on extendedProps
                }

                return $arr;
            })->values();
        } catch (\Throwable $__e) {
            \Illuminate\Support\Facades\Log::warning('EventController: failed to enrich events for Inertia render', ['error' => $__e->getMessage()]);
            $mappedForInertia = $events;
        }

        return Inertia::render('Calendar/Index', [
            'events' => $mappedForInertia,
            'date' => $date,
            'user_id' => $baseUserId,
            'jobs' => [],
        ]);
    }

    // 予定の新規作成
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'event_item_type_id' => 'nullable|exists:event_item_types,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'startHour' => 'required',
            'startMinute' => 'required',
            'endHour' => 'required',
            'endMinute' => 'required',
            'interrupted_event_ids' => 'nullable|array',
            'interrupted_event_ids.*' => 'integer',
        ]);
        $data['user_id'] = Auth::id();
        // Debug: log that store was invoked and incoming data to help trace why assignments are not marked
        try {
            Log::info('EventController::store invoked', ['input' => $request->all(), 'job_id' => $request->input('job_id')]);
        } catch (\Throwable $__logE) {
            // ignore logging errors
        }
        // If this request is for a job assignment, and that assignment is already marked scheduled,
        // prevent creating a duplicate Event.
        $jobId = $request->input('job_id');
        if ($jobId) {
            try {
                // events.project_job_assignment_id は project_job_assignment_by_myself の FK
                $existingAssignment = ProjectJobAssignmentByMyself::find($jobId);
                if ($existingAssignment) {
                    if (Schema::hasColumn('project_job_assignment_by_myself', 'scheduled') && $existingAssignment->scheduled) {
                        // Redirect back to assigned-jobs with a flash message indicating it's already set
                        return redirect()->route('user.assigned-jobs.index')->with('error', 'このジョブは既にセット済です。');
                    }
                }
            } catch (\Throwable $__e) {
                // ignore and proceed
            }
        }
        // 開始・終了時刻を結合
        $data['start'] = $data['date'] . ' ' . $data['startHour'] . ':' . $data['startMinute'] . ':00';
        $data['end'] = $data['date'] . ' ' . $data['endHour'] . ':' . $data['endMinute'] . ':00';

        // If the `events` table has a `date` column, ensure it's present; otherwise avoid
        // persisting `date` to prevent SQL errors on schemas that don't include it.
        if (Schema::hasColumn('events', 'date')) {
            $data['date'] = $data['date'] ?? date('Y-m-d', strtotime($data['start']));
        } else {
            unset($data['date']);
        }

        $event = new Event();
        $event->user_id = Auth::id();
        $event->event_item_type_id = $data['event_item_type_id'] ?? null;
        $event->title = $data['title'];
        $event->description = $data['description'];
        $event->start = $data['start'];
        $event->end = $data['end'];
        if (isset($data['date'])) {
            $event->date = $data['date'];
        }
        // If job_id provided and events table has project_job_assignment_id, set it so the event links to assignment
        try {
            if ($jobId && Schema::hasColumn('events', 'project_job_assignment_id')) {
                $event->project_job_assignment_id = $jobId;
            }
        } catch (\Throwable $__e) {
            // ignore environment/schema checks
        }
        $event->save();

        // own_interruption_minutes: 新しいイベント自体が「長い（差し込まれた）側」の場合に設定される重複時間
        // Create.vue で「新しいイベント >= 既存イベント」と判定されたときに送られる
        try {
            $ownInterruptionMins = (int) $request->input('own_interruption_minutes', 0);
            if ($ownInterruptionMins > 0 && Schema::hasColumn('events', 'interruption_minutes')) {
                $event->interruption_minutes = ($event->interruption_minutes ?? 0) + $ownInterruptionMins;
                $event->save();
            }
        } catch (\Throwable $__e) {
            Log::warning('EventController: failed to set own_interruption_minutes', ['error' => $__e->getMessage()]);
        }

        // 中断イベントの interruption_minutes を更新（差し込み作業による中断時間を記録）
        $interruptedEventIds = $request->input('interrupted_event_ids', []);
        if (!empty($interruptedEventIds)) {
            $newStart = Carbon::parse($data['start']);
            $newEnd   = Carbon::parse($data['end']);
            foreach ($interruptedEventIds as $interruptedId) {
                try {
                    $interruptedEvent = Event::where('id', (int) $interruptedId)
                        ->where('user_id', Auth::id())
                        ->first();
                    if ($interruptedEvent && $interruptedEvent->starts_at && $interruptedEvent->ends_at) {
                        $evStart = Carbon::parse($interruptedEvent->starts_at);
                        $evEnd   = Carbon::parse($interruptedEvent->ends_at);
                        $overlapStart = $newStart->gt($evStart) ? $newStart : $evStart;
                        $overlapEnd   = $newEnd->lt($evEnd)    ? $newEnd   : $evEnd;
                        $overlapMins  = max(0, (int) (($overlapEnd->timestamp - $overlapStart->timestamp) / 60));
                        if ($overlapMins > 0) {
                            $interruptedEvent->increment('interruption_minutes', $overlapMins);
                        }
                    }
                } catch (\Throwable $__e) {
                    Log::warning('EventController: failed to update interruption_minutes', [
                        'interrupted_event_id' => $interruptedId,
                        'error' => $__e->getMessage(),
                    ]);
                }
            }
        }

        // If this event was created for a job assignment, mark that assignment as scheduled.
        $jobId = $request->input('job_id');
        if ($jobId) {
            try {
                // events.project_job_assignment_id は project_job_assignment_by_myself の FK
                $assignment = ProjectJobAssignmentByMyself::find($jobId);
                if ($assignment) {
                    // Log current assignment scheduled state before updating for debugging
                    try {
                        Log::info('EventController: about to mark ProjectJobAssignment scheduled', [
                            'assignment_id' => $assignment->id ?? null,
                            'scheduled' => $assignment->scheduled ?? null,
                            'scheduled_at' => isset($assignment->scheduled_at) ? (string)$assignment->scheduled_at : null,
                        ]);
                    } catch (\Throwable $__logEx) {
                        // ignore logging errors
                    }

                    // Use a transaction to ensure consistency
                    DB::transaction(function () use ($assignment, $event) {
                        // If the assignments table has scheduled_at column, set it to event start
                        if (Schema::hasColumn('project_job_assignment_by_myself', 'scheduled_at')) {
                            $assignment->scheduled_at = $event->start;
                        }
                        // If there's a boolean scheduled flag, set it true
                        if (Schema::hasColumn('project_job_assignment_by_myself', 'scheduled')) {
                            $assignment->scheduled = true;
                        }
                        // If the assignments table keeps a separate `date` column, and it's empty,
                        // populate it with the event's date portion so calendar-based flows have a date.
                        if (Schema::hasColumn('project_job_assignment_by_myself', 'date') && empty($assignment->date)) {
                            $assignment->date = date('Y-m-d', strtotime($event->start));
                        }
                        // If statuses table exists, set scheduled status
                        try {
                            if (Schema::hasTable('statuses') && Schema::hasColumn('project_job_assignment_by_myself', 'status_id')) {
                                $status = DB::table('statuses')->where('key', 'scheduled')->first();
                                if (!$status) {
                                    $statusId = DB::table('statuses')->insertGetId(['key' => 'scheduled', 'name' => 'セット済み', 'created_at' => now(), 'updated_at' => now()]);
                                } else {
                                    $statusId = $status->id;
                                }
                                $assignment->status_id = $statusId;
                            }
                        } catch (\Throwable $__e) {
                            // non-fatal
                        }
                        $assignment->save();
                    });

                    // Update any related JobAssignmentMessage rows so JobBox entries
                    // reflect that a schedule was set for this assignment.
                    try {
                        \App\Models\JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)
                            ->where(function ($q) use ($event) {
                                // only update those not already marked scheduled
                                $q->whereNull('scheduled')->orWhere('scheduled', false);
                            })
                            ->update(['scheduled' => true, 'scheduled_at' => $event->start]);
                    } catch (\Throwable $__e) {
                        Log::warning('EventController: failed to update JobAssignmentMessage scheduled flag', ['error' => $__e->getMessage()]);
                    }

                    // After marking scheduled, decide whether to create an internal Message
                    // or to emit a lightweight toast event. If the actor (Auth::id()) is
                    // the assignee (the user responsible for doing the job), we DO NOT
                    // create a Message for the jobbox; instead we broadcast an
                    // AssignmentStatusToast event for client-side toast display.
                    try {
                        $assignment->load('projectJob');
                        $assigneeId = $assignment->user_id ?? null;
                        $actorId = Auth::id();

                        // Prepare toast payload common fields
                        $toastPayload = [
                            'assignment_id' => $assignment->id,
                            'event_id' => $event->id ?? null,
                            'action' => 'scheduled',
                            'actor_id' => $actorId,
                            'actor_name' => Auth::user() ? (Auth::user()->name ?? null) : null,
                            'title' => $event->title ?? ($assignment->title ?? ($assignment->projectJob->name ?? null)),
                        ];

                        if ($assigneeId && $actorId && intval($assigneeId) === intval($actorId)) {
                            // Actor is the assignee: broadcast lightweight toast instead of Message
                            try {
                                event(new \App\Events\AssignmentStatusToast($toastPayload));
                                Log::info('EventController: AssignmentStatusToast broadcast (scheduled)', ['assignment_id' => $assignment->id, 'event_id' => $event->id ?? null, 'actor_id' => $actorId]);
                            } catch (\Throwable $__e) {
                                Log::warning('EventController: failed to broadcast AssignmentStatusToast (scheduled)', ['error' => $__e->getMessage(), 'assignment_id' => $assignment->id, 'actor_id' => $actorId]);
                            }
                        } else {
                            // Actor is not the assignee: create a persistent internal Message as before
                            $assignerId = null;
                            if (Schema::hasColumn('project_job_assignments', 'created_by') && isset($assignment->created_by)) {
                                $assignerId = $assignment->created_by;
                            }
                            if (!$assignerId && $assignment->projectJob && isset($assignment->projectJob->user_id)) {
                                $assignerId = $assignment->projectJob->user_id;
                            }

                            if ($assignerId) {
                                $sanitizer = app(HtmlSanitizer::class);
                                $bodyLines = [];
                                $bodyLines[] = "ジョブ割り当て終了のご連絡";
                                $bodyLines[] = "プロジェクトジョブID: " . ($assignment->project_job_id ?? '-');
                                $bodyLines[] = "予定をセットしたユーザーID: " . ($actorId ?? '-');
                                $bodyLines[] = "イベント名: " . ($event->title ?? ($assignment->title ?: ($assignment->projectJob->name ?? '-')));
                                $bodyLines[] = "開始: " . ($event->start ?? '-');
                                $bodyLines[] = "終了: " . ($event->end ?? '-');
                                $detailText = $assignment->detail ?? ($assignment->projectJob->detail ?? '');
                                $bodyLines[] = "詳細: " . ($detailText ?: '');

                                $body = implode("\n", $bodyLines);
                                $clean = $sanitizer->purify($body);

                                try {
                                    $message = Message::create([
                                        'from_user_id' => $actorId,
                                        'subject' => 'ジョブ割り当て終了',
                                        'body' => $clean,
                                        'status' => 'sent',
                                        'sent_at' => now(),
                                    ]);
                                    MessageRecipient::create([
                                        'message_id' => $message->id,
                                        'user_id' => $assignerId,
                                        'type' => 'to',
                                    ]);
                                    try {
                                        $message->load('recipients');
                                        event(new MessageCreated($message));
                                        Log::info('EventController: Message created and broadcast (scheduled)', ['message_id' => $message->id, 'assignment_id' => $assignment->id, 'actor_id' => $actorId]);
                                    } catch (\Throwable $__e) {
                                        Log::warning('EventController: broadcast MessageCreated failed', ['error' => $__e->getMessage(), 'message_id' => $message->id ?? null]);
                                    }
                                } catch (\Throwable $__e) {
                                    Log::error('EventController: failed to create Message/Recipient', ['error' => $__e->getMessage()]);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send set-complete message or toast', ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to mark ProjectJobAssignment as scheduled', ['job_id' => $jobId, 'error' => $e->getMessage()]);
            }
        }

        // 添付ファイル保存
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $isImage = strpos($file->getMimeType(), 'image') === 0;
                $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $ext = $file->getClientOriginalExtension();
                $dateStr = date('Ymd', strtotime($event->start));
                $uniqueName = $original . '_' . $dateStr . $event->id . '.' . $ext;
                $path = 'event_attachments/' . $uniqueName;

                if ($isImage) {
                    // @phpstan-ignore-next-line
                    /** @var \Intervention\Image\Image $img */
                    if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
                        $manager = ImageManager::imagick();
                    } else {
                        $manager = ImageManager::gd();
                    }
                    $img = $manager->read($file);
                    if ($img->width() > 1200) {
                        $img->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    if (strtolower($ext) === 'png') {
                        $enc = new \Intervention\Image\Encoders\PngEncoder();
                    } else {
                        $enc = new \Intervention\Image\Encoders\JpegEncoder(80);
                    }
                    $encoded = $img->encode($enc);
                    Storage::disk('public')->put($path, (string) $encoded->toDataUri() ? base64_decode(preg_replace('#^data:.*?;base64,#', '', $encoded->toDataUri())) : (string) $encoded);
                    try {
                        Storage::disk('public')->setVisibility($path, 'public');
                        $real = Storage::disk('public')->path($path) ?? null;
                        if ($real && file_exists($real)) {
                            @chmod($real, 0644);
                        }
                    } catch (\Throwable $_exPerm) {
                        logger()->warning('EventController: could not set permissions for image', ['path' => $path, 'error' => $_exPerm->getMessage()]);
                    }
                } else {
                    Storage::disk('public')->putFileAs('event_attachments', $file, $uniqueName);
                }
                // Create a generic Attachment and attach via polymorphic pivot to this Event
                $attachment = \App\Models\Attachment::create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
                try {
                    $event->attachments()->attach($attachment->id, ['created_at' => now(), 'updated_at' => now()]);
                } catch (\Throwable $_ex) {
                    logger()->warning('EventController: could not attach attachment to event', ['attachment_id' => $attachment->id, 'event_id' => $event->id, 'error' => $_ex->getMessage()]);
                }
            }
        }

        // proof_schedule 自動連動
        $this->syncProofScheduleFromEvent($event);

        // Q-03: サーバー側で重複分を正確に再計算（フロントエンド計算を上書き）
        $this->recalcInterruptionMinutes($event);

        return redirect()->route('calendar.index');
    }

    // 予定の更新
    public function update(Request $request, Event $event)
    {
        // debug logging removed
        $this->authorize('update', $event);
        // 会議室予約に紐づく予定はこの汎用編集経路では扱わない（ScheduleEventController と同様のガード）
        if ($event->room_reservation_id) {
            abort(422, 'この予定は会議室予約に紐づいています。会議室予約から編集してください');
        }
        // debug logging removed
        // $request->get()は引数必須のため削除
        $data = $request->validate([
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'startHour' => 'required',
            'startMinute' => 'required',
            'endHour' => 'required',
            'endMinute' => 'required',
        ]);
        $dateStr  = $data['date'];
        $newStart = $dateStr . ' ' . $data['startHour'] . ':' . $data['startMinute'] . ':00';
        $newEnd   = $dateStr . ' ' . $data['endHour']   . ':' . $data['endMinute']   . ':00';

        // 旧時刻を記録（recalc で旧重複範囲の解除に使用）
        $oldStart = $event->getRawOriginal('starts_at');
        $oldEnd   = $event->getRawOriginal('ends_at');

        // update_from_calendar() と同じ方式でモデル経由で保存
        $event->title       = $data['title'];
        $event->description = $data['description'] ?? '';
        $event->start       = $newStart;
        $event->end         = $newEnd;
        $event->save();

        // 紐づく project_job_assignment の時間フィールドも同期
        if (Schema::hasColumn('events', 'project_job_assignment_id') && $event->project_job_assignment_id) {
            try {
                $assignment = \App\Models\ProjectJobAssignment::withoutGlobalScopes()->find($event->project_job_assignment_id);
                if ($assignment) {
                    // desired_end_date（締め切り日）・desired_time（締め切り時刻）はイベント時間編集で変更しない
                    // end_time カラムは存在しないため終了時刻は同期しない
                    $assignment->start_time = sprintf('%02d:%02d', $data['startHour'], $data['startMinute']);
                    $assignment->save();
                }
            } catch (\Throwable $__e) {
                \Illuminate\Support\Facades\Log::warning('EventController::update: failed to sync time to assignment', ['error' => $__e->getMessage(), 'event_id' => $event->id]);
            }
        }
        // debug logging removed

        // 添付ファイル保存（追加分のみ）
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $isImage = strpos($file->getMimeType(), 'image') === 0;
                $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $ext = $file->getClientOriginalExtension();
                $dateStr = date('Ymd', strtotime($event->start));
                $uniqueName = $original . '_' . $dateStr . $event->id . '.' . $ext;
                $path = 'event_attachments/' . $uniqueName;

                if ($isImage) {
                    // @phpstan-ignore-next-line
                    /** @var \Intervention\Image\Image $img */
                    if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
                        $manager = ImageManager::imagick();
                    } else {
                        $manager = ImageManager::gd();
                    }
                    $img = $manager->read($file);
                    if ($img->width() > 1200) {
                        $img->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    if (strtolower($ext) === 'png') {
                        $enc = new \Intervention\Image\Encoders\PngEncoder();
                    } else {
                        $enc = new \Intervention\Image\Encoders\JpegEncoder(80);
                    }
                    $encoded = $img->encode($enc);
                    Storage::disk('public')->put($path, (string) $encoded->toDataUri() ? base64_decode(preg_replace('#^data:.*?;base64,#', '', $encoded->toDataUri())) : (string) $encoded);
                    try {
                        Storage::disk('public')->setVisibility($path, 'public');
                        $real = Storage::disk('public')->path($path) ?? null;
                        if ($real && file_exists($real)) {
                            @chmod($real, 0644);
                        }
                    } catch (\Throwable $_exPerm) {
                        logger()->warning('EventController: could not set permissions for image', ['path' => $path, 'error' => $_exPerm->getMessage()]);
                    }
                } else {
                    Storage::disk('public')->putFileAs('event_attachments', $file, $uniqueName);
                }
                $attachment = \App\Models\Attachment::create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
                try {
                    $event->attachments()->attach($attachment->id, ['created_at' => now(), 'updated_at' => now()]);
                } catch (\Throwable $_ex) {
                    logger()->warning('EventController: could not attach attachment to event', ['attachment_id' => $attachment->id, 'event_id' => $event->id, 'error' => $_ex->getMessage()]);
                }
            }
        }
        // proof_schedule 自動連動
        $this->syncProofScheduleFromEvent($event);

        // Q-01: 重複分の再計算（旧時間帯の解除 + 新時間帯の計算）
        $this->recalcInterruptionMinutes($event, $oldStart, $oldEnd);

        return redirect()->back()->with('success', 'イベントを更新しました。');
    }

    // 予定の削除
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        // 会議室予約に紐づく予定はこの汎用削除経路では扱わない（ScheduleEventController と同様のガード）
        // room_reservations.event_id には FK が無いため、ガード無しで削除すると孤立レコードが残る
        if ($event->room_reservation_id) {
            abort(422, 'この予定は会議室予約に紐づいています。会議室予約から削除してください');
        }

        // Q-02: 削除前に重複していたイベントIDを記録（削除後の波及再計算に使用）
        $overlappingBeforeDelete = collect();
        try {
            if ($event->starts_at && $event->ends_at) {
                $rawS = $event->getRawOriginal('starts_at');
                $rawE = $event->getRawOriginal('ends_at');
                if ($rawS && $rawE) {
                    $overlappingBeforeDelete = Event::where('user_id', $event->user_id)
                        ->where('id', '!=', $event->id)
                        ->whereNotNull('starts_at')
                        ->whereNotNull('ends_at')
                        ->where('starts_at', '<', Carbon::parse($rawE)->addDay()->toDateTimeString())
                        ->where('ends_at', '>', Carbon::parse($rawS)->subDay()->toDateTimeString())
                        ->with('projectJobAssignment:id,job_type')
                        ->get(['id', 'user_id', 'starts_at', 'ends_at', 'project_job_assignment_id']);
                }
            }
        } catch (\Throwable $e) { /* non-fatal */ }
        // 添付ファイルも削除（エラーが出ても本体削除を続行）
        try {
            foreach ($event->attachments as $attachment) {
                if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                    Storage::disk('public')->delete($attachment->path);
                }
                $attachment->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('EventController::destroy attachment deletion failed', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }
        // イベント削除後、同じ assignment に紐づくイベントがなくなった場合は ProgressCell のリンクもクリア
        $assignmentIdToCheck = null;
        try {
            if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                $assignmentIdToCheck = $event->project_job_assignment_id;
            }
        } catch (\Throwable $e) { /* ignore */ }

        // proof_schedule 連動削除
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('proof_schedules')) {
                // ① event_id で直接紐づいているものを削除
                \App\Models\ProofSchedule::where('event_id', $event->id)->delete();
                // ② フォールバック: event_id 未設定でも user_id + starts_at + ends_at が一致するものを削除
                $rawStart = $event->getRawOriginal('starts_at');
                $rawEnd   = $event->getRawOriginal('ends_at');
                if ($event->user_id && $rawStart && $rawEnd) {
                    \App\Models\ProofSchedule::where('user_id', $event->user_id)
                        ->where('starts_at', $rawStart)
                        ->where('ends_at', $rawEnd)
                        ->whereNull('event_id')
                        ->delete();
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        $event->delete();

        // Q-02: 削除後の波及再計算（重複していたイベントの interruption_minutes を更新）
        try {
            foreach ($overlappingBeforeDelete as $ov) {
                $this->recalcSingleStoredInterruption($ov);
            }
        } catch (\Throwable $e) { /* non-fatal */ }

        if ($assignmentIdToCheck) {
            try {
                $remaining = \App\Models\Event::where('project_job_assignment_id', $assignmentIdToCheck)->count();
                if ($remaining === 0) {
                    // progress_cells のリンクをクリア
                    \App\Models\ProgressCell::where('assignment_id', $assignmentIdToCheck)
                        ->update(['assignment_id' => null]);

                    // PJA-B（マイジョブ）を取得し、元の依頼ジョブ（PJA-A）があればリセット
                    $myJobAssignment = \App\Models\ProjectJobAssignment::find($assignmentIdToCheck);
                    if ($myJobAssignment && $myJobAssignment->supersedes_assignment_id) {
                        // PJA-A を未スケジュール状態に戻す → pendingRequests に再表示される
                        \App\Models\ProjectJobAssignment::where('id', $myJobAssignment->supersedes_assignment_id)
                            ->update([
                                'accepted'     => false,
                                'scheduled'    => false,
                                'scheduled_at' => null,
                            ]);
                        Log::info('EventController::destroy: original requested assignment reset', [
                            'original_assignment_id' => $myJobAssignment->supersedes_assignment_id,
                            'my_job_assignment_id'   => $assignmentIdToCheck,
                        ]);
                    }

                    // PJA-B（マイジョブ）を削除 → マイジョブ一覧から消え、依頼されたジョブに戻る
                    if ($myJobAssignment) {
                        $myJobAssignment->delete();
                        Log::info('EventController::destroy: my-job assignment deleted', [
                            'assignment_id' => $assignmentIdToCheck,
                        ]);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            // 校正スロット（pja101）の最後のイベント削除時: pja101/pja100 削除 + ProofRequest を pending に戻す
            try {
                \App\Services\ProofJobRollbackService::rollbackIfNoEvents($assignmentIdToCheck);
            } catch (\Throwable $e) { /* ignore */ }
        }

        // If this request came from Inertia, return a redirect (Inertia expects a redirect/Inertia response).
        if (request()->header('X-Inertia')) {
            $returnTo = request()->input('return_to');
            if ($returnTo && str_starts_with((string)$returnTo, '/')) {
                return redirect($returnTo);
            }
            return redirect()->route('calendar.index');
        }
        return response()->json(['message' => 'deleted']);
    }

    // イベント詳細表示
    public function show(Event $event)
    {
        // 認可チェック: 他ユーザーのイベントをCoordinatorが見る場合
        // → 案件のリーダー（user_id）またはサブCo（project_job_coordinators）のみ許可
        $authUser = Auth::user();
        if ($authUser && $event->user_id && (int) $event->user_id !== (int) $authUser->id) {
            $role = $authUser->user_role ?? '';
            if (! in_array($role, ['admin', 'superadmin', 'leader'])) {
                $hasAccess = false;
                try {
                    if ($event->project_job_assignment_id) {
                        $assignment = ProjectJobAssignment::withoutGlobalScopes()
                            ->select('project_job_id')
                            ->find($event->project_job_assignment_id);
                        if ($assignment && $assignment->project_job_id) {
                            $pjId = $assignment->project_job_id;
                            $isOwner = DB::table('project_jobs')
                                ->where('id', $pjId)
                                ->where('user_id', $authUser->id)
                                ->exists();
                            $isSubCo = DB::table('project_job_coordinators')
                                ->where('project_job_id', $pjId)
                                ->where('user_id', $authUser->id)
                                ->exists();
                            $hasAccess = $isOwner || $isSubCo;
                        }
                    }
                } catch (\Throwable $__e) {
                    // non-fatal: テーブル未存在等
                }
                if (! $hasAccess) {
                    abort(403, 'このイベントを閲覧する権限がありません。案件のリーダーまたはサブコーディネーターのみ閲覧できます。');
                }
            }
        }

        // 添付ファイルも取得する場合はリレーションをロード
        $event->load(['attachments', 'eventItemType']);
        // if this event is linked to a project job assignment, eager-load that assignment with all lookup relations
        if (Schema::hasColumn('events', 'project_job_assignment_id') && $event->project_job_assignment_id) {
            $event->load([
                'projectJobAssignment.projectJob.client',
                'projectJobAssignment.workItemType',
                'projectJobAssignment.size',
                'projectJobAssignment.stage',
                'projectJobAssignment.statusModel',
                'projectJobAssignment.difficultyModel',
                'projectJobAssignment.user',
                'projectJobAssignment.sender',
            ]);

            // MyJob に関連する coordinator assignment を検索して付加する
            $byMyself = $event->projectJobAssignment;
            $coordinatorAssignmentInfo = null;
            if ($byMyself) {
                try {
                    // 同じ project_job_id + user_id で sender_id != user_id のレコードを検索
                    //（Coordinatorからの依頼ジョブを特定）
                    $cAssignment = ProjectJobAssignment::where('project_job_id', $byMyself->project_job_id)
                        ->where('user_id', $byMyself->user_id)
                        ->where(function ($query) use ($byMyself) {
                            $query->where('sender_id', '!=', $byMyself->user_id)
                                ->orWhereNull('sender_id');
                        })
                        ->where('id', '!=', $byMyself->id) // 自身は除外
                        ->first();
                    
                    if ($cAssignment) {
                        $coordinatorAssignmentInfo = [
                            'id' => $cAssignment->id,
                            'title' => $cAssignment->title,
                            'completed' => (bool) ($cAssignment->completed ?? false),
                        ];
                    }
                } catch (\Throwable $__e) {
                    \Illuminate\Support\Facades\Log::warning('EventController: Failed to find coordinator assignment', [
                        'error' => $__e->getMessage(),
                        'assignment_id' => $byMyself->id ?? null,
                    ]);
                }
            }
        }
        // JST 時刻を正確に取得（proof イベントは UTC 保存、一般は JST 保存）
        if (!$event->relationLoaded('projectJobAssignment')) {
            $event->load('projectJobAssignment:id,job_type');
        }
        $evStartJst = $this->resolveJstCarbon($event, 'starts_at');
        $evEndJst   = $this->resolveJstCarbon($event, 'ends_at');

        // Q-04: 昼休憩計算を共通メソッドに委譲
        $lunchStart = null;
        $lunchEnd   = null;
        $lunchOverlapMinutes = 0;
        try {
            if ($evStartJst && $evEndJst && $event->user_id) {
                $lunchCache = [];
                $lunchOverlapMinutes = $this->computeLunchMinutes($evStartJst, $evEndJst, (int) $event->user_id, $lunchCache);
                // 表示用: 昼休憩時刻を取得
                $evDate = $evStartJst->toDateString();
                $bi = $lunchCache[$evDate] ?? null;
                if ($bi) {
                    $lunchStart = $bi['start'];
                    $lunchEnd   = $bi['end'];
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        $proofRequested = false;
        try {
            if ($event->project_job_assignment_id) {
                $proofRequested = \App\Models\ProofRequest::where('project_job_assignment_id', $event->project_job_assignment_id)
                    ->whereNotIn('status', ['completed'])
                    ->exists();
            }
        } catch (\Throwable $e) {}

        $chainSeries = $this->computeChainSeries($event);

        // ── Q-07: リアルタイム重複計算（resolveJstCarbon で UTC/JST 混在を正しく処理）──
        $overlappingEvents = [];
        $dynamicInterruptionMinutes = 0;
        try {
            if ($evStartJst && $evEndJst) {
                $myDurationMins = abs((int) $evEndJst->diffInMinutes($evStartJst));
                $windowStart = $evStartJst->copy()->subDay()->toDateTimeString();
                $windowEnd   = $evEndJst->copy()->addDay()->toDateTimeString();

                $candidates = Event::where('user_id', $event->user_id)
                    ->where('id', '!=', $event->id)
                    ->whereNotNull('starts_at')
                    ->whereNotNull('ends_at')
                    ->where('starts_at', '<', $windowEnd)
                    ->where('ends_at', '>', $windowStart)
                    ->with('projectJobAssignment:id,job_type')
                    ->get(['id', 'title', 'starts_at', 'ends_at', 'project_job_assignment_id']);

                foreach ($candidates as $ov) {
                    $ovStart = $this->resolveJstCarbon($ov, 'starts_at');
                    $ovEnd   = $this->resolveJstCarbon($ov, 'ends_at');
                    if (!$ovStart || !$ovEnd) continue;
                    if (!$ovStart->lt($evEndJst) || !$ovEnd->gt($evStartJst)) continue;

                    $ovDurationMins = abs((int) $ovEnd->diffInMinutes($ovStart));
                    $overlapStart   = $evStartJst->gt($ovStart) ? $evStartJst : $ovStart;
                    $overlapEnd     = $evEndJst->lt($ovEnd)    ? $evEndJst   : $ovEnd;
                    $overlapMins    = max(0, (int) $overlapStart->diffInMinutes($overlapEnd, false));
                    if ($overlapMins <= 0) continue;

                    if ($myDurationMins >= $ovDurationMins) {
                        $dynamicInterruptionMinutes += $overlapMins;
                        $overlappingEvents[] = [
                            'id'           => $ov->id,
                            'title'        => $ov->title,
                            'overlap_mins' => $overlapMins,
                            'direction'    => 'self',
                        ];
                    } else {
                        $overlappingEvents[] = [
                            'id'           => $ov->id,
                            'title'        => $ov->title,
                            'overlap_mins' => $overlapMins,
                            'direction'    => 'other',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EventController::show: failed to compute dynamic overlaps', ['error' => $e->getMessage()]);
        }
        // ─────────────────────────────────────────────────────────────────────

        $hideEdit = request()->query('hide_edit') ? true : false;
        return Inertia::render('Events/Show', [
            'event'                        => $event,
            'jst_start'                    => $evStartJst?->format('Y-m-d H:i'),
            'jst_end'                      => $evEndJst?->format('Y-m-d H:i'),
            'hide_edit'                    => $hideEdit,
            'coordinator_assignment'       => $coordinatorAssignmentInfo ?? null,
            'lunch_start'                  => $lunchStart,
            'lunch_end'                    => $lunchEnd,
            'lunch_overlap_minutes'        => $lunchOverlapMinutes,
            'proof_requested'              => $proofRequested,
            'chain_series'                 => $chainSeries,
            'overlapping_events'           => $overlappingEvents,
            'dynamic_interruption_minutes' => $dynamicInterruptionMinutes,
        ]);
    }

    /**
     * Coordinator 専用イベント詳細: show() と同じデータを返すが
     * view_as_coordinator=true を渡し、タブメニューを coordinator に固定させる。
     * 編集・削除ボタンは非表示（完了ボタンは有効）。
     */
    public function showForCoordinator(Event $event)
    {
        $authUser = Auth::user();
        if (! $authUser) {
            return redirect()->route('login');
        }
        // 認可チェック: 案件オーナー・サブCo・admin/superadmin/leader のみ
        $allowed = in_array($authUser->user_role ?? '', ['admin', 'superadmin', 'leader', 'coordinator']);
        if (! $allowed) {
            abort(403);
        }

        $event->load(['attachments', 'eventItemType']);
        if (Schema::hasColumn('events', 'project_job_assignment_id') && $event->project_job_assignment_id) {
            $event->load([
                'projectJobAssignment.projectJob.client',
                'projectJobAssignment.workItemType',
                'projectJobAssignment.size',
                'projectJobAssignment.stage',
                'projectJobAssignment.statusModel',
                'projectJobAssignment.difficultyModel',
                'projectJobAssignment.user',
                'projectJobAssignment.sender',
            ]);
        }

        // JST 時刻を正確に取得（projectJobAssignment は上の load で済み）
        $evStartJst = $this->resolveJstCarbon($event, 'starts_at');
        $evEndJst   = $this->resolveJstCarbon($event, 'ends_at');

        // Q-04: 昼休憩計算を共通メソッドに委譲
        $lunchStart = null;
        $lunchEnd   = null;
        $lunchOverlapMinutes = 0;
        try {
            if ($evStartJst && $evEndJst && $event->user_id) {
                $lunchCache = [];
                $lunchOverlapMinutes = $this->computeLunchMinutes($evStartJst, $evEndJst, (int) $event->user_id, $lunchCache);
                $evDate = $evStartJst->toDateString();
                $bi = $lunchCache[$evDate] ?? null;
                if ($bi) {
                    $lunchStart = $bi['start'];
                    $lunchEnd   = $bi['end'];
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        $chainSeries = $this->computeChainSeries($event);

        // ── Q-07: リアルタイム重複計算（resolveJstCarbon 対応）──
        $overlappingEvents = [];
        $dynamicInterruptionMinutes = 0;
        try {
            if ($evStartJst && $evEndJst) {
                $myDurationMins = abs((int) $evEndJst->diffInMinutes($evStartJst));
                $windowStart = $evStartJst->copy()->subDay()->toDateTimeString();
                $windowEnd   = $evEndJst->copy()->addDay()->toDateTimeString();

                $candidates = Event::where('user_id', $event->user_id)
                    ->where('id', '!=', $event->id)
                    ->whereNotNull('starts_at')
                    ->whereNotNull('ends_at')
                    ->where('starts_at', '<', $windowEnd)
                    ->where('ends_at', '>', $windowStart)
                    ->with('projectJobAssignment:id,job_type')
                    ->get(['id', 'title', 'starts_at', 'ends_at', 'project_job_assignment_id']);

                foreach ($candidates as $ov) {
                    $ovStart = $this->resolveJstCarbon($ov, 'starts_at');
                    $ovEnd   = $this->resolveJstCarbon($ov, 'ends_at');
                    if (!$ovStart || !$ovEnd) continue;
                    if (!$ovStart->lt($evEndJst) || !$ovEnd->gt($evStartJst)) continue;

                    $ovDurationMins = abs((int) $ovEnd->diffInMinutes($ovStart));
                    $overlapStart   = $evStartJst->gt($ovStart) ? $evStartJst : $ovStart;
                    $overlapEnd     = $evEndJst->lt($ovEnd)    ? $evEndJst   : $ovEnd;
                    $overlapMins    = max(0, (int) $overlapStart->diffInMinutes($overlapEnd, false));
                    if ($overlapMins <= 0) continue;

                    if ($myDurationMins >= $ovDurationMins) {
                        $dynamicInterruptionMinutes += $overlapMins;
                        $overlappingEvents[] = [
                            'id'           => $ov->id,
                            'title'        => $ov->title,
                            'overlap_mins' => $overlapMins,
                            'direction'    => 'self',
                        ];
                    } else {
                        $overlappingEvents[] = [
                            'id'           => $ov->id,
                            'title'        => $ov->title,
                            'overlap_mins' => $overlapMins,
                            'direction'    => 'other',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('EventController::showForCoordinator: failed to compute dynamic overlaps', ['error' => $e->getMessage()]);
        }
        // ─────────────────────────────────────────────────────────────────────

        return Inertia::render('Events/Show', [
            'event'                        => $event,
            'jst_start'                    => $evStartJst?->format('Y-m-d H:i'),
            'jst_end'                      => $evEndJst?->format('Y-m-d H:i'),
            'hide_edit'                    => true,
            'view_as_coordinator'          => true,
            'coordinator_assignment'       => null,
            'lunch_start'                  => $lunchStart,
            'lunch_end'                    => $lunchEnd,
            'lunch_overlap_minutes'        => $lunchOverlapMinutes,
            'chain_series'                 => $chainSeries,
            'overlapping_events'           => $overlappingEvents,
            'dynamic_interruption_minutes' => $dynamicInterruptionMinutes,
        ]);
    }

    /**
     * イベントに紐づく続きジョブチェーン情報を計算して返す共通メソッド。
     * show() / showForCoordinator() 両方で使用する。
     */

    private function computeChainSeries(Event $event): ?array
    {
        $chainSeries = null;
        try {
            $currentPjaId = $event->project_job_assignment_id ?? null;
            if ($currentPjaId) {
                $pja = ProjectJobAssignment::find($currentPjaId);
                if ($pja) {
                    // ルートをたどる
                    $root = $pja;
                    for ($i = 0; $i < 20; $i++) {
                        if (empty($root->source_assignment_id)) break;
                        $parent = ProjectJobAssignment::find($root->source_assignment_id);
                        if (!$parent) break;
                        $root = $parent;
                    }
                    // ルートから全子孫を BFS で収集
                    $allIds = collect([$root->id]);
                    $toProcess = collect([$root->id]);
                    for ($i = 0; $i < 20 && $toProcess->isNotEmpty(); $i++) {
                        $children = ProjectJobAssignment::whereIn('source_assignment_id', $toProcess->toArray())->pluck('id');
                        $children->each(fn($id) => $allIds->push($id));
                        $toProcess = $children;
                    }
                    $allIds = $allIds->unique()->values();

                    // チェーンが2件以上の場合のみ付加
                    if ($allIds->count() > 1) {
                        $chainPjas = ProjectJobAssignment::whereIn('id', $allIds->toArray())
                            ->select(['id', 'title', 'source_assignment_id', 'completed', 'desired_end_date', 'user_id'])
                            ->orderBy('created_at')
                            ->get();

                        $allEvents = \App\Models\Event::whereIn('project_job_assignment_id', $allIds->toArray())
                            ->select(['id', 'project_job_assignment_id', 'starts_at', 'ends_at', 'interruption_minutes'])
                            ->get()
                            ->groupBy('project_job_assignment_id');

                        $calcActualMins = function ($ev, $breakInfo) {
                            $rawS = $ev->starts_at ?? null;
                            $rawE = $ev->ends_at   ?? null;
                            if (!$rawS || !$rawE) return 0;
                            $evS = Carbon::parse($rawS);
                            $evE = Carbon::parse($rawE);
                            $total = max(0, (int) $evS->diffInMinutes($evE, false));
                            $interrupt = (int) ($ev->interruption_minutes ?? 0);
                            $lunch = 0;
                            if ($breakInfo) {
                                $date = $evS->toDateString();
                                $ls = Carbon::parse($date . ' ' . $breakInfo['start']);
                                $le = Carbon::parse($date . ' ' . $breakInfo['end']);
                                $os = $evS->gt($ls) ? $evS : $ls;
                                $oe = $evE->lt($le) ? $evE : $le;
                                $lunch = max(0, (int) $os->diffInMinutes($oe, false));
                            }
                            return max(0, $total - $interrupt - $lunch);
                        };

                        $seriesItems = [];
                        $seriesTotalMinutes = 0;
                        foreach ($chainPjas as $cpja) {
                            $cpjaEvents = $allEvents->get($cpja->id, collect());
                            $breakInfoForUser = null;
                            try {
                                $userSetting = UserSetting::where('user_id', $cpja->user_id)->first();
                                $breakInfoForUser = [
                                    'start' => $userSetting?->lunch_start ?: '12:00',
                                    'end'   => $userSetting?->lunch_end   ?: '13:00',
                                ];
                            } catch (\Throwable $__e) {}

                            $pjaMins = 0;
                            $evList = [];
                            foreach ($cpjaEvents as $ev) {
                                $mins = $calcActualMins($ev, $breakInfoForUser);
                                $pjaMins += $mins;
                                $rawS = $ev->starts_at ?? null;
                                $rawE = $ev->ends_at   ?? null;
                                $evList[] = [
                                    'id'      => $ev->id,
                                    'date'    => $rawS ? Carbon::parse($rawS)->setTimezone('Asia/Tokyo')->toDateString() : null,
                                    'start'   => $rawS ? Carbon::parse($rawS)->setTimezone('Asia/Tokyo')->format('H:i') : null,
                                    'end'     => $rawE ? Carbon::parse($rawE)->setTimezone('Asia/Tokyo')->format('H:i') : null,
                                    'minutes' => $mins,
                                ];
                            }
                            $seriesTotalMinutes += $pjaMins;
                            $seriesItems[] = [
                                'assignment_id'        => $cpja->id,
                                'title'                => $cpja->title,
                                'completed'            => (bool) ($cpja->completed ?? false),
                                'is_current'           => $cpja->id === $currentPjaId,
                                'source_assignment_id' => $cpja->source_assignment_id,
                                'minutes'              => $pjaMins,
                                'events'               => $evList,
                            ];
                        }
                        $chainSeries = [
                            'items'         => $seriesItems,
                            'total_minutes' => $seriesTotalMinutes,
                        ];
                    }
                }
            }
        } catch (\Throwable $__e) {
            \Illuminate\Support\Facades\Log::warning('EventController: chain calculation failed', ['error' => $__e->getMessage()]);
        }
        return $chainSeries;
    }

    /**
     * Show an event in the Diaries/Interactions context (read-only view).
     * This route is intended for admin/leader diary interactions pages so the
     * rendered page omits edit affordances and uses the diary interactions layout.
     */
    public function showForInteraction(Event $event)
    {
        $event->load('attachments');
        if (Schema::hasColumn('events', 'project_job_assignment_id') && $event->project_job_assignment_id) {
            $event->load('projectJobAssignment.projectJob.client');
        }
        // Always hide edit for interactions context
        $diaryId = request()->query('diary');
        return Inertia::render('Diaries/Interactions/EventShow', [
            'event' => $event,
            'diary_id' => $diaryId,
        ]);
    }

    /**
     * Mark an event (linked to a project_job_assignment) as completed.
     * This will set project_job_assignments.completed = true, update related
     * JobAssignmentMessage rows, optionally prefix the event title with the
     * completion label, and broadcast a notification to relevant recipients.
     */
    public function complete(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        if (!Schema::hasColumn('events', 'project_job_assignment_id') || !$event->project_job_assignment_id) {
            return redirect()->back()->with('error', 'このイベントはジョブに紐づいていません。');
        }

        // フロントのモーダルで「Coordinator割当も完了にする」を選択したかどうか
        $alsoCompleteCoordinator = filter_var($request->input('also_complete_coordinator', false), FILTER_VALIDATE_BOOLEAN);

        try {
            DB::beginTransaction();

            // events.project_job_assignment_id は project_job_assignment_by_myself の FK
            $assignment = ProjectJobAssignmentByMyself::find($event->project_job_assignment_id);
            if (!$assignment) {
                DB::rollBack();
                return redirect()->back()->with('error', '関連する割り当てが見つかりません。');
            }

            // ── by_myself を完了にする ────────────────────────────────────────
            // NOTE: ProjectJobAssignmentByMyself のテーブルは project_job_assignments。
            // 旧テーブル名 'project_job_assignment_by_myself' ではなく実テーブルを参照する。
            $assignmentTable = $assignment->getTable();
            if (Schema::hasColumn($assignmentTable, 'completed')) {
                $assignment->completed = true;
            }
            $completedStatusId = null;
            try {
                if (Schema::hasTable('statuses') && Schema::hasColumn($assignmentTable, 'status_id')) {
                    $status = DB::table('statuses')->where('key', 'completed')->first();
                    if (!$status) {
                        $completedStatusId = DB::table('statuses')->insertGetId(['key' => 'completed', 'name' => '完了', 'created_at' => now(), 'updated_at' => now()]);
                    } else {
                        $completedStatusId = $status->id;
                    }
                    $assignment->status_id = $completedStatusId;
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }
            $assignment->save();

            // ジョブ通知（進行管理表リンクあり → リーダーへ / なし → Coordinator依頼分のみ）
            try {
                $user = $request->user();
                $pj   = $assignment->projectJob
                    ?? \App\Models\ProjectJob::find($assignment->project_job_id);
                if ($user && $pj) {
                    // 自分自身 or ソースチェーン（祖先）に ProgressCell があれば progress_completed 通知
                    $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->exists();
                    if (!$hasProgressLink && !empty($assignment->source_assignment_id)) {
                        // 祖先を辿って ProgressCell を探す（深さ最大 20）
                        $cur = $assignment;
                        for ($__i = 0; $__i < 20 && !$hasProgressLink; $__i++) {
                            if (empty($cur->source_assignment_id)) break;
                            $parent = \App\Models\ProjectJobAssignment::find($cur->source_assignment_id);
                            if (!$parent) break;
                            $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $parent->id)->exists();
                            $cur = $parent;
                        }
                    }
                    if ($hasProgressLink) {
                        \App\Services\JobNotificationService::notifyProgressCompleted($user, $pj, $assignment);
                    } else {
                        \App\Services\JobNotificationService::notifyCompleted($user, $assignment, $pj);
                    }
                }
            } catch (\Throwable $__eNotify) {
                \Illuminate\Support\Facades\Log::warning('EventController: JobNotification dispatch error', ['error' => $__eNotify->getMessage()]);
            }

            // チェーン上のすべての元ジョブ（祖先）も完了にする（ProgressCell が祖先を参照している場合に必要）
            $current = $assignment;
            for ($__i = 0; $__i < 20; $__i++) {
                if (empty($current->source_assignment_id)) break;
                $parent = \App\Models\ProjectJobAssignment::find($current->source_assignment_id);
                if (!$parent) break;
                if (!$parent->completed) {
                    $parent->completed = true;
                    $parent->save();
                }
                $current = $parent;
            }

            // 校正ジョブが完了になった場合、対応するProofRequestを自動完了しpja_operatorにproof_completed_atを設定する
            // 旧フロー: coordinator_assignment_id 経由（pja101 → pja100）
            // 新フロー: supersedes_assignment_id 経由（マイジョブ → proof pja100）
            if (!empty($assignment->job_type) && $assignment->job_type === 'proof' && !empty($assignment->coordinator_assignment_id)) {
                try {
                    $pja100 = \App\Models\ProjectJobAssignment::find($assignment->coordinator_assignment_id);
                    if ($pja100) {
                        $proofRequest = \App\Models\ProofRequest::where('project_job_id', $pja100->project_job_id)
                            ->where('proofreader_id', $pja100->user_id)
                            ->where('proof_coordinator_id', $pja100->sender_id)
                            ->whereIn('status', ['assigned', 'in_progress'])
                            ->latest()
                            ->first();
                        if ($proofRequest) {
                            $proofRequest->update([
                                'status'       => 'completed',
                                'completed_at' => now(),
                            ]);
                            if ($proofRequest->project_job_assignment_id) {
                                \App\Models\ProjectJobAssignment::where('id', $proofRequest->project_job_assignment_id)
                                    ->whereNull('proof_completed_at')
                                    ->update(['proof_completed_at' => now()]);
                            }
                            $pja100->completed = true;
                            $pja100->save();
                            Log::info('EventController: proof job (coordinator_assignment_id) completed → ProofRequest completed', [
                                'pja_id'           => $assignment->id,
                                'proof_request_id' => $proofRequest->id,
                            ]);
                        }
                    }
                } catch (\Throwable $__eProof) {
                    Log::warning('EventController: failed to complete ProofRequest on proof job completion', ['error' => $__eProof->getMessage()]);
                }
            } elseif (!empty($assignment->supersedes_assignment_id)) {
                // 新フロー: マイジョブ（supersedes proof pja100）が完了した場合
                try {
                    $pja100 = \App\Models\ProjectJobAssignment::find($assignment->supersedes_assignment_id);
                    if ($pja100 && $pja100->job_type === 'proof') {
                        $proofRequest = \App\Models\ProofRequest::where('project_job_id', $pja100->project_job_id)
                            ->where('proofreader_id', $pja100->user_id)
                            ->whereIn('status', ['assigned', 'in_progress'])
                            ->latest()
                            ->first();
                        if ($proofRequest) {
                            $proofRequest->update([
                                'status'       => 'completed',
                                'completed_at' => now(),
                            ]);
                            if ($proofRequest->project_job_assignment_id) {
                                \App\Models\ProjectJobAssignment::where('id', $proofRequest->project_job_assignment_id)
                                    ->whereNull('proof_completed_at')
                                    ->update(['proof_completed_at' => now()]);
                            }
                            $pja100->completed = true;
                            $pja100->save();
                            Log::info('EventController: proof job (supersedes_assignment_id) completed → ProofRequest completed', [
                                'pja_id'           => $assignment->id,
                                'proof_request_id' => $proofRequest->id,
                            ]);
                        }
                    }
                } catch (\Throwable $__eProofNew) {
                    Log::warning('EventController: failed to complete ProofRequest (supersedes path)', ['error' => $__eProofNew->getMessage()]);
                }
            }

            // ── Coordinator の project_job_assignments を自動的に完了にする ──
            // （関連するCoordinator割当レコードがある場合は常に更新）
            try {
                if (!$completedStatusId && Schema::hasTable('statuses')) {
                    $completedStatus = DB::table('statuses')->where('key', 'completed')->orWhere('slug', 'completed')->first();
                    $completedStatusId = $completedStatus ? $completedStatus->id : null;
                }

                // supersedes_assignment_id で紐づいた特定のCoordinator割当のみを完了にする
                // （同一案件・同一ユーザーの全割当を完了にする旧ロジックは他のジョブを誤完了させるバグがあったため廃止）
                $coordinatorAssignments = collect();
                if (!empty($assignment->supersedes_assignment_id)) {
                    // MyJobが明示的に supersedes している Coordinator割当のみ
                    $target = ProjectJobAssignment::find($assignment->supersedes_assignment_id);
                    if ($target && $target->id !== $assignment->id) {
                        $coordinatorAssignments = collect([$target]);
                    }
                } elseif (!empty($assignment->coordinator_assignment_id)) {
                    // 校正ジョブ等の coordinator_assignment_id 経由
                    $target = ProjectJobAssignment::find($assignment->coordinator_assignment_id);
                    if ($target && $target->id !== $assignment->id) {
                        $coordinatorAssignments = collect([$target]);
                    }
                }
                // supersedes も coordinator_assignment_id もない場合は自動完了しない

                $updatedCoordinatorIds = [];
                foreach ($coordinatorAssignments as $cAssignment) {
                    // 既に完了済みの場合はスキップ
                    if ($cAssignment->completed) {
                        continue;
                    }

                    if (Schema::hasColumn('project_job_assignments', 'completed')) {
                        $cAssignment->completed = true;
                    }
                    if ($completedStatusId && Schema::hasColumn('project_job_assignments', 'status_id')) {
                        $cAssignment->status_id = $completedStatusId;
                    }
                    $cAssignment->save();
                    $updatedCoordinatorIds[] = $cAssignment->id;

                    // job_assignment_messages も同期
                    if (Schema::hasColumn('job_assignment_messages', 'completed')) {
                        \App\Models\JobAssignmentMessage::where('project_job_assignment_id', $cAssignment->id)
                            ->update(['completed' => true]);
                    }
                    if ($completedStatusId && Schema::hasColumn('job_assignment_messages', 'status_id')) {
                        \App\Models\JobAssignmentMessage::where('project_job_assignment_id', $cAssignment->id)
                            ->update(['status_id' => $completedStatusId]);
                    }
                }

                if (count($updatedCoordinatorIds) > 0) {
                    Log::info('EventController: auto-completed coordinator assignments', [
                        'my_job_id' => $assignment->id,
                        'updated_coordinator_ids' => $updatedCoordinatorIds,
                    ]);
                }
            } catch (\Throwable $__e) {
                Log::warning('EventController: failed to auto-complete coordinator assignments', [
                    'error' => $__e->getMessage(),
                    'assignment_id' => $assignment->id ?? null,
                ]);
            }
            // ─────────────────────────────────────────────────────────────────────

            // progress_cells.completed_at を更新（ジョブ完了と進行表を同期）
            try {
                $progressCellAssignmentIds = [$assignment->id];
                if (isset($updatedCoordinatorIds) && count($updatedCoordinatorIds) > 0) {
                    $progressCellAssignmentIds = array_merge($progressCellAssignmentIds, $updatedCoordinatorIds);
                }
                \App\Models\ProgressCell::whereIn('assignment_id', array_unique(array_filter($progressCellAssignmentIds)))
                    ->whereNull('completed_at')
                    ->update(['completed_at' => now()]);
            } catch (\Throwable $__ePc) {
                Log::warning('EventController: failed to update progress_cells.completed_at', [
                    'error' => $__ePc->getMessage(),
                    'assignment_id' => $assignment->id ?? null,
                ]);
            }

            // Prefix event title with completion marker for persistent visibility
            $prefix = '【完了】';
            if (strpos($event->title, $prefix) !== 0) {
                $event->title = $prefix . $event->title;
                $event->save();
            }

            // Create an internal Message to notify assigner/coordinators or emit a toast
            $didBroadcastToast = false;
            try {
                $actorId = Auth::id();
                $assigneeId = $assignment->user_id ?? null;
                $toastPayload = [
                    'assignment_id' => $assignment->id,
                    'event_id' => $event->id ?? null,
                    'action' => 'completed',
                    'actor_id' => $actorId,
                    'actor_name' => Auth::user() ? (Auth::user()->name ?? null) : null,
                    'title' => $event->title ?? ($assignment->title ?? ($assignment->projectJob->name ?? null)),
                ];

                if ($assigneeId && $actorId && intval($assigneeId) === intval($actorId)) {
                    // Actor is assignee: broadcast a lightweight toast instead of creating a Message
                    try {
                        event(new \App\Events\AssignmentStatusToast($toastPayload));
                        $didBroadcastToast = true;
                        Log::info('EventController: AssignmentStatusToast broadcast (completed)', ['assignment_id' => $assignment->id, 'event_id' => $event->id ?? null, 'actor_id' => $actorId]);
                    } catch (\Throwable $__e) {
                        Log::warning('EventController: failed to broadcast AssignmentStatusToast (completed)', ['error' => $__e->getMessage(), 'assignment_id' => $assignment->id, 'actor_id' => $actorId]);
                    }
                } else {
                    // Actor is not the assignee: create Message and notify recipients as before
                    $sanitizer = app(HtmlSanitizer::class);
                    $bodyLines = [];
                    $bodyLines[] = "ジョブが完了しました";
                    $bodyLines[] = "プロジェクトジョブID: " . ($assignment->project_job_id ?? '-');
                    $bodyLines[] = "完了を操作したユーザーID: " . ($actorId ?? '-');
                    $bodyLines[] = "イベント名: " . ($event->title ?? '-');
                    $bodyLines[] = "開始: " . ($event->start ?? '-');
                    $bodyLines[] = "終了: " . ($event->end ?? '-');
                    $bodyLines[] = "詳細: " . ($event->description ?? '');
                    $body = $sanitizer->purify(implode("\n", $bodyLines));

                    $message = Message::create([
                        'from_user_id' => $actorId,
                        'subject' => 'ジョブ完了',
                        'body' => $body,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $recipientIds = [];
                    $assignment->load('projectJob');
                    if ($assignment->projectJob && $assignment->projectJob->user_id) $recipientIds[] = $assignment->projectJob->user_id;
                    if (Schema::hasColumn('project_job_assignments', 'created_by') && $assignment->created_by && !in_array($assignment->created_by, $recipientIds)) {
                        $recipientIds[] = $assignment->created_by;
                    }
                    $recipientIds = array_values(array_unique(array_filter($recipientIds)));

                    foreach ($recipientIds as $uid) {
                        MessageRecipient::create(['message_id' => $message->id, 'user_id' => $uid, 'type' => 'to']);
                    }

                    try {
                        $message->load('recipients');
                        event(new MessageCreated($message));
                        Log::info('EventController: Message created and broadcast (completed)', ['message_id' => $message->id, 'assignment_id' => $assignment->id, 'actor_id' => $actorId]);
                    } catch (\Throwable $__e) {
                        Log::warning('EventController: broadcast MessageCreated failed', ['error' => $__e->getMessage(), 'message_id' => $message->id ?? null]);
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal: continue
            }

            DB::commit();

            // If we broadcast a lightweight toast (assignee actor case), avoid adding
            // a flash success message which could trigger a second toast on the recipient.
            if ($didBroadcastToast) {
                return redirect()->back();
            }

            return redirect()->back()->with('success', '完了にしました。通知を送信しました。');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('error', '完了処理中にエラーが発生しました。');
        }
    }

    // イベント新規作成画面表示
    public function create(Request $request)
    {
        $date        = $request->query('date', now()->toDateString());
        $startHour   = $request->query('startHour');
        $startMinute = $request->query('startMinute');
        $endHour     = $request->query('endHour');
        $endMinute   = $request->query('endMinute');
        $jobId = $request->query('job');
        $jobData = null;
        if ($jobId) {
            // load lookup relations so Create page can prefill human-friendly labels
            // events.project_job_assignment_id は project_job_assignment_by_myself の FK
            $assignment = \App\Models\ProjectJobAssignmentByMyself::with(['projectJob.client', 'projectJob', 'user', 'size', 'stage', 'workItemType', 'statusModel'])->find($jobId);
            if ($assignment) {
                // Use model helper to produce consistent prefill data
                $jobData = $assignment->toEventPrefill();
            }
        }
        // Debug logging to ensure jobData is created and query params are received
        // create-time debug logging removed
        // Gather user-scoped clients and projects (those where the current user is a project_team_member)
        $user = $request->user();
        $userClients = [];
        $userProjects = [];
        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobsFromTeam = $ptms->map(function ($ptm) {
                return $ptm->projectJob;
            })->filter();
            // 案件リーダーとして紐づいている案件も含める
            $jobsAsLeader = \App\Models\ProjectJob::with('client')
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->get();
            // 副リーダー（project_job_coordinators）として紐づいている案件も含める
            $jobsAsSubLeader = \App\Models\ProjectJob::with('client')
                ->whereHas('coordinators', fn($q) => $q->where('users.id', $user->id))
                ->where('completed', false)
                ->get();
            $jobs = $jobsFromTeam->merge($jobsAsLeader)->merge($jobsAsSubLeader)->unique('id');

            $userProjects = $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title ?? ($job->name ?? null),
                    'client_id' => $job->client ? $job->client->id : null,
                ];
            })->values();

            $clients = $jobs->map(function ($job) {
                return $job->client;
            })->filter()->unique('id')->values();

            $userClients = $clients->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? null)];
            })->values();
        } catch (\Throwable $__e) {
            // ignore if tables/relations unavailable
            $userClients = [];
            $userProjects = [];
        }

        // Provide minimal members array containing current user so AssignmentForm can default assignment to self
        $members = [];
        try {
            if ($user) {
                $members = [['id' => $user->id, 'name' => $user->name]];
            }
        } catch (\Throwable $__e) {
            $members = [];
        }

        // If the authenticated user belongs to a company/department, include them so
        // front-end components (AssignmentForm_user.vue) can default company/department
        // for users who cannot change them (non-superadmin).
        $company = null;
        $department = null;
        try {
            if ($user && isset($user->company_id) && $user->company_id) {
                $company = \App\Models\Company::find($user->company_id);
            }
            if ($user && isset($user->department_id) && $user->department_id) {
                $department = \App\Models\Department::find($user->department_id);
            }
        } catch (\Throwable $__e) {
            // ignore lookup errors; front-end will fallback to auth.user values
            $company = null;
            $department = null;
        }

        // lookup lists so front-end components (AssignmentForm_user) can render selects
        $types = [];
        $sizes = [];
        $stages = [];
        $statuses = [];
        try {
            $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
            $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
            $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
            $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        } catch (\Throwable $__e) {
            // ignore lookup errors; frontend will handle empty lists
            $types = [];
            $sizes = [];
            $stages = [];
            $statuses = [];
        }

        $eventItemTypes = EventItemType::orderBy('sort_order')->get(['id', 'name', 'slug', 'coefficient']);

        $props = [
            'date'           => $date,
            'startHour'      => $startHour   ? str_pad($startHour,   2, '0', STR_PAD_LEFT) : null,
            'startMinute'    => $startMinute ? str_pad($startMinute, 2, '0', STR_PAD_LEFT) : null,
            'endHour'        => $endHour     ? str_pad($endHour,     2, '0', STR_PAD_LEFT) : null,
            'endMinute'      => $endMinute   ? str_pad($endMinute,   2, '0', STR_PAD_LEFT) : null,
            'job'            => $jobData,
            'userClients'    => $userClients,
            'userProjects'   => $userProjects,
            'members'        => $members,
            'company'        => $company,
            'department'     => $department,
            'types'          => $types,
            'sizes'          => $sizes,
            'stages'         => $stages,
            'statuses'       => $statuses,
            'eventItemTypes' => $eventItemTypes,
        ];

        // Prepare a simplified debug-friendly copy of props so logs are readable
        $props_debug = $props;
        try {
            if ($company && is_object($company)) {
                $props_debug['company'] = ['id' => $company->id ?? null, 'name' => $company->name ?? null];
            }
            if ($department && is_object($department)) {
                $props_debug['department'] = ['id' => $department->id ?? null, 'name' => $department->name ?? null];
            }
        } catch (\Throwable $__e) {
            // ignore
        }

        try {
            \Illuminate\Support\Facades\Log::info('INERTIA_PROPS', ['props' => $props_debug]);
        } catch (\Throwable $__e) {
            // ignore logging errors
        }

        return Inertia::render('Events/Create', $props);
    }

    /**
     * ジョブ作成専用ページ表示
     */
    public function createJob(Request $request)
    {
        // reuse much of create() logic but render a dedicated job creation page
        $date = $request->query('date', now()->toDateString());
        $jobId = $request->query('job');
        // JobBox/Show から「予定をセット（カレンダー）」で来た場合、Coordinator割当IDが渡される
        $sourceJobAssignmentId = $request->query('source_job_assignment_id');
        if (!$jobId && $sourceJobAssignmentId) {
            $jobId = $sourceJobAssignmentId;
        }
        $prefillTitle = $request->query('title'); // 進行管理表からの遷移時にタイトルをprefill
        $prefillClientId = $request->query('client_id');       // 進行管理表: クライアントID
        $prefillProjectJobId = $request->query('project_job_id'); // 進行管理表: 案件ID
        $prefillStageId = $request->query('stage_id');          // 進行管理表: ステージID
        $prefillSizeId = $request->query('size_id');             // 進行管理表: サイズID
        $prefillWorkItemTypeId = $request->query('work_item_type_id'); // 進行管理表: 作業種別ID
        $prefillProgressSheetId  = $request->query('progress_sheet_id');  // User進行表: シートID
        $prefillWorkflowSheetId  = $request->query('workflow_sheet_id'); // 管理シート: シートID
        $prefillRowId = $request->query('row_id');               // 進行表/管理シート: 行ID
        $prefillColKey = $request->query('col_key');             // 進行表/管理シート: 列キー
        $startHour = $request->query('startHour');
        $startMinute = $request->query('startMinute');
        $endHour = $request->query('endHour');
        $endMinute = $request->query('endMinute');
        $jobData = null;
        $jobAssignments = null; // prefill data for AssignmentForm_user
        if ($jobId) {
            $assignment = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'projectJob', 'user', 'size', 'stage', 'workItemType', 'statusModel'])->find($jobId);
            if ($assignment) {
                $jobData = $assignment->toEventPrefill();
                // Build assignments prefill with IDs for dropdowns, amounts intentionally null
                $jobAssignments = [[
                    'id' => null,
                    'coordinator_assignment_id' => $assignment->id,
                    'project_job_id' => $assignment->project_job_id,
                    '_client_id' => $assignment->projectJob?->client?->id ?? '',
                    'title_suffix' => $assignment->title ?? '',
                    'detail' => $assignment->detail ?? '',
                    'difficulty_id' => $assignment->difficulty_id ?? null,
                    'desired_start_date' => $date, // today
                    'desired_end_date' => $assignment->desired_end_date
                        ? (method_exists($assignment->desired_end_date, 'format') ? $assignment->desired_end_date->format('Y-m-d') : (string) $assignment->desired_end_date)
                        : null,
                    'desired_time' => $assignment->desired_time ?? null,
                    'estimated_hours' => $assignment->estimated_hours ?? null,
                    'work_item_type_id' => $assignment->work_item_type_id ?? null,
                    'size_id' => $assignment->size_id ?? null,
                    'stage_id' => $assignment->stage_id ?? null,
                    // If this page was opened from JobBox (source_job_assignment_id present),
                    // prefill amounts from the coordinator assignment so the recipient sees quantity.
                    // Otherwise keep amounts empty (daily work recorded separately).
                    'amounts' => $sourceJobAssignmentId ? ($assignment->amounts ?? null) : null,
                    'amounts_unit' => $sourceJobAssignmentId ? ($assignment->amounts_unit ?? 'page') : 'page',
                    // JobBox/Show から「マイジョブとして登録」で来た場合は依頼ジョブを supersede
                    'supersedes_assignment_id' => $sourceJobAssignmentId ? (int)$sourceJobAssignmentId : null,
                ]];

                // 進行表セル情報を取得して prefill に追加（依頼ジョブが進行表と紐づいている場合）
                if ($sourceJobAssignmentId) {
                    try {
                        $progressCellForJob = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->first();
                        if ($progressCellForJob) {
                            $jobAssignments[0]['_progress_sheet_id'] = $progressCellForJob->progress_sheet_id ?? null;
                            $jobAssignments[0]['_row_id'] = $progressCellForJob->row_id ?? null;
                            $jobAssignments[0]['_col_key'] = $progressCellForJob->col_key ?? null;
                        }
                    } catch (\Throwable $__e) {
                        // non-fatal: continue without progress cell info
                    }
                }
            }
        }

        // 進行管理表/管理シート joblink 経由でタイトルまたは sheet_id が渡された場合（$jobId なし）、prefill assignments を構築
        if (!$jobId && ($prefillTitle || $prefillProgressSheetId || $prefillWorkflowSheetId)) {
            $jobAssignments = [[
                'id' => null,
                'project_job_id' => $prefillProjectJobId ?? null,
                '_client_id' => $prefillClientId ? (string)$prefillClientId : '',
                'title' => $prefillTitle ?? '',
                'stage_id' => $prefillStageId ?? null,
                'size_id' => $prefillSizeId ?? null,
                'work_item_type_id' => $prefillWorkItemTypeId ?? null,
                '_locked_client' => (bool)$prefillClientId,
                '_locked_project' => (bool)$prefillProjectJobId,
                '_locked_stage' => (bool)$prefillStageId,
                '_locked_size' => (bool)$prefillSizeId,
                '_locked_work_item_type' => (bool)$prefillWorkItemTypeId,
                '_progress_sheet_id'  => $prefillProgressSheetId  ? (int)$prefillProgressSheetId  : null,
                '_workflow_sheet_id'  => $prefillWorkflowSheetId  ? (int)$prefillWorkflowSheetId  : null,
                '_row_id'  => $prefillRowId  ? (int)$prefillRowId  : null,
                '_col_key' => $prefillColKey ?? null,
            ]];
        }

        $user = $request->user();
        $userClients = [];
        $userProjects = [];
        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobsFromTeam = $ptms->map(function ($ptm) {
                return $ptm->projectJob;
            })->filter()->filter(fn($j) => !$j->completed);

            // 案件リーダー（project_jobs.user_id）として紐づいている案件も含める
            $jobsAsLeader = \App\Models\ProjectJob::with('client')
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->get();
            // 副リーダー（project_job_coordinators）として紐づいている案件も含める
            $jobsAsSubLeader = \App\Models\ProjectJob::with('client')
                ->whereHas('coordinators', fn($q) => $q->where('users.id', $user->id))
                ->where('completed', false)
                ->get();

            $jobs = $jobsFromTeam->merge($jobsAsLeader)->merge($jobsAsSubLeader)->unique('id');

            $userProjects = $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title ?? ($job->name ?? null),
                    'client_id' => $job->client ? $job->client->id : null,
                ];
            })->values();

            $clients = $jobs->map(function ($job) {
                return $job->client;
            })->filter()->unique('id')->values();

            $userClients = $clients->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? null)];
            })->values();
        } catch (\Throwable $__e) {
            $userClients = [];
            $userProjects = [];
        }

        // 「その他」クライアント・プロジェクトを全社共通で確保し、リストの末尾に追加
        $otherClientId = null;
        $otherProjectId = null;
        try {
            $otherClient = \App\Models\Client::firstOrCreate(
                ['name' => 'その他', 'company_id' => null],
                ['notes' => 'デフォルト「その他」クライアント']
            );
            $otherProject = \App\Models\ProjectJob::firstOrCreate(
                ['title' => 'その他', 'client_id' => $otherClient->id],
                ['detail' => 'デフォルト「その他」案件']
            );
            $otherClientId = $otherClient->id;
            $otherProjectId = $otherProject->id;

            $userClients = collect($userClients);
            if (!$userClients->contains('id', $otherClientId)) {
                $userClients = $userClients->push(['id' => $otherClientId, 'name' => 'その他']);
            }
            $userClients = $userClients->values()->toArray();

            $userProjects = collect($userProjects);
            if (!$userProjects->contains('id', $otherProjectId)) {
                $userProjects = $userProjects->push(['id' => $otherProjectId, 'title' => 'その他', 'client_id' => $otherClientId]);
            }
            $userProjects = $userProjects->values()->toArray();
        } catch (\Throwable $__e) {
            // 取得失敗時はそのまま続行
        }

        // 進行表からのprefill: client/projectがuserListsに含まれていない場合は先頭に追加する
        // （CoordinatorがTeamMember未登録でも正しい名前が表示されるように）
        try {
            if ($prefillClientId) {
                $userClients = collect($userClients);
                if (!$userClients->contains('id', (int)$prefillClientId)) {
                    $pfClient = \App\Models\Client::find((int)$prefillClientId);
                    if ($pfClient) {
                        $userClients = $userClients->prepend(['id' => $pfClient->id, 'name' => $pfClient->name ?? '']);
                    }
                }
                $userClients = $userClients->values()->toArray();
            }
            if ($prefillProjectJobId) {
                $userProjects = collect($userProjects);
                if (!$userProjects->contains('id', (int)$prefillProjectJobId)) {
                    $pfProject = \App\Models\ProjectJob::find((int)$prefillProjectJobId);
                    if ($pfProject) {
                        $userProjects = $userProjects->prepend([
                            'id' => $pfProject->id,
                            'title' => $pfProject->title ?? '',
                            'client_id' => $pfProject->client_id,
                        ]);
                    }
                }
                $userProjects = $userProjects->values()->toArray();
            }
        } catch (\Throwable $__e) {
            // 取得失敗時はそのまま続行
        }

        $members = [];
        try {
            if ($user) {
                $members = [['id' => $user->id, 'name' => $user->name]];
            }
        } catch (\Throwable $__e) {
            $members = [];
        }

        $company = null;
        $department = null;
        try {
            if ($user && isset($user->company_id) && $user->company_id) {
                $company = \App\Models\Company::find($user->company_id);
            }
            if ($user && isset($user->department_id) && $user->department_id) {
                $department = \App\Models\Department::find($user->department_id);
            }
        } catch (\Throwable $__e) {
            $company = null;
            $department = null;
        }

        $types = [];
        $sizes = [];
        $stages = [];
        $statuses = [];
        // 各テーブルのクエリは独立した try-catch で囲む（1つ失敗しても他に影響しないよう）
        try {
            $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        } catch (\Throwable $__e) { $types = []; }
        try {
            $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        } catch (\Throwable $__e) { $sizes = []; }
        try {
            $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        } catch (\Throwable $__e) {
            try {
                $stages = \App\Models\Stage::orderBy('id')->get(['id', 'name']);
            } catch (\Throwable $__e2) { $stages = []; }
        }
        try {
            $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        } catch (\Throwable $__e) { $statuses = []; }

        $difficulties = [];
        try {
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);
        } catch (\Throwable $__e) { $difficulties = []; }

        // 新規登録時のデフォルトステータス：「進行中」(slug=in_progress) のIDを取得
        $inProgressStatusId = collect($statuses)->firstWhere('slug', 'in_progress')?->id ?? null;

        $prefillEvent = null;
        if ($date) {
            $prefillEvent = [
                'start' => $date,
                'end' => $date,
                'desired_start_date' => $date,
            ];
            if ($startHour !== null && $startHour !== '' && $startMinute !== null && $startMinute !== '') {
                $sh = str_pad($startHour, 2, '0', STR_PAD_LEFT);
                $sm = str_pad($startMinute, 2, '0', STR_PAD_LEFT);
                $prefillEvent['start'] = "{$date} {$sh}:{$sm}:00";
                $prefillEvent['start_time'] = "{$sh}:{$sm}";
            }
            if ($endHour !== null && $endHour !== '' && $endMinute !== null && $endMinute !== '') {
                $eh = str_pad($endHour, 2, '0', STR_PAD_LEFT);
                $em = str_pad($endMinute, 2, '0', STR_PAD_LEFT);
                $prefillEvent['end'] = "{$date} {$eh}:{$em}:00";
                $prefillEvent['desired_time'] = "{$eh}:{$em}";
            }
        }

        $props = [
            'date' => $date,
            'prefill_title' => $prefillTitle, // 進行管理表joblink経由の場合にタイトルをprefill
            'job' => $jobData,
            'assignments' => $jobAssignments, // pre-filled from coordinator assignment (amounts=null)
            'userClients' => $userClients,
            'userProjects' => $userProjects,
            'other_client_id' => $otherClientId,
            'other_project_id' => $otherProjectId,
            'in_progress_status_id' => $inProgressStatusId,
            'members' => $members,
            'company' => $company,
            'department' => $department,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            'prefillEvent' => $prefillEvent,
        ];

        try {
            \Illuminate\Support\Facades\Log::info('INERTIA_PROPS createJob', ['props' => ['date' => $date, 'jobId' => $jobId]]);
        } catch (\Throwable $__e) {
        }

        return Inertia::render('Events/Create_Job', $props);
    }

    /**
     * Send a test "job completion" email to user_id=1 with dummy data.
     * Useful for verifying mail delivery during development.
     */
    public function sendTestCompletion(Request $request)
    {
        // prepare dummy payload
        $data = [
            'project_job_id' => $request->input('project_job_id', 12345),
            'set_by_user_id' => $request->input('set_by_user_id', Auth::id() ?? 2),
            'event_title' => $request->input('event_title', 'テストイベント'),
            'start' => $request->input('start', now()->addDay()->format('Y-m-d H:i:00')),
            'end' => $request->input('end', now()->addDay()->addHour()->format('Y-m-d H:i:00')),
            'details' => $request->input('details', "これはテスト用のジョブ割り当て完了通知です。詳細はここに記載されます。"),
        ];

        $recipient = User::find(1);
        if (!$recipient || !$recipient->email) {
            return response()->json(['error' => 'Recipient user_id=1 not found or has no email'], 500);
        }

        $bodyLines = [];
        $bodyLines[] = "ジョブ割り当て終了のご連絡";
        $bodyLines[] = "プロジェクトジョブID: " . $data['project_job_id'];
        $bodyLines[] = "予定をセットしたユーザーID: " . $data['set_by_user_id'];
        $bodyLines[] = "イベント名: " . $data['event_title'];
        $bodyLines[] = "開始: " . $data['start'];
        $bodyLines[] = "終了: " . $data['end'];
        $bodyLines[] = "詳細:\n" . $data['details'];

        $body = implode("\n", $bodyLines);

        try {
            // Create as an application Message (internal messages flow)
            $message = Message::create([
                'from_user_id' => $data['set_by_user_id'],
                'subject' => 'ジョブ割り当て終了',
                'body' => $body,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            MessageRecipient::create([
                'message_id' => $message->id,
                'user_id' => $recipient->id,
                'type' => 'to',
            ]);

            // Log for environments where DB access from CLI is not available
            try {
                Log::info('sendTestCompletion created Message', ['message_id' => $message->id, 'recipient' => $recipient->id]);
            } catch (\Throwable $__e) {
            }

            // Broadcast to Reverb via MessageCreated event for real-time notification
            try {
                $message->load('recipients');
                event(new MessageCreated($message));
            } catch (\Throwable $__e) {
                Log::warning('Broadcast MessageCreated failed', ['error' => $__e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create test Message', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Message create failed', 'message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Test Message created and broadcast (recipient user_id=1: ' . $recipient->email . ')', 'payload' => $data]);
    }
    // イベント編集画面表示
    public function edit(Event $event)
    {
        $event->date = \Carbon\Carbon::parse($event->start)->toDateString();

        // If this event is linked to a project job assignment, render the
        // JobBox edit page which allows editing the canonical assignment
        // alongside the event. Otherwise fall back to the normal Events/Edit.
        try {
            if (Schema::hasColumn('events', 'project_job_assignment_id') && $event->project_job_assignment_id) {
                // events.project_job_assignment_id は project_job_assignment_by_myself の FK
                $assignment = ProjectJobAssignmentByMyself::with(['projectJob.client', 'projectJob'])->find($event->project_job_assignment_id);

                // 孤立イベント（割り当て削除済み）→ 汎用編集ページへ
                if (!$assignment) {
                    return Inertia::render('Events/Edit', ['event' => $event]);
                }

                // Gather same lookup lists as create/createJob so the form has selects
                $user = request()->user();
                $userClients = [];
                $userProjects = [];
                try {
                    $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                        ->where('user_id', $user->id)
                        ->get();
                    $jobsFromTeam = $ptms->map(function ($ptm) {
                        return $ptm->projectJob;
                    })->filter();
                    // 案件リーダーとして紐づいている案件も含める
                    $jobsAsLeader = \App\Models\ProjectJob::with('client')
                        ->where('user_id', $user->id)
                        ->where('completed', false)
                        ->get();
                    // 副リーダー（project_job_coordinators）として紐づいている案件も含める
                    $jobsAsSubLeader = \App\Models\ProjectJob::with('client')
                        ->whereHas('coordinators', fn($q) => $q->where('users.id', $user->id))
                        ->where('completed', false)
                        ->get();
                    $jobs = $jobsFromTeam->merge($jobsAsLeader)->merge($jobsAsSubLeader)->unique('id');
                    $userProjects = $jobs->map(function ($job) {
                        return [
                            'id' => $job->id,
                            'title' => $job->title ?? ($job->name ?? null),
                            'client_id' => $job->client ? $job->client->id : null,
                        ];
                    })->values();
                    $clients = $jobs->map(function ($job) {
                        return $job->client;
                    })->filter()->unique('id')->values();
                    $userClients = $clients->map(function ($c) {
                        return ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? null)];
                    })->values();
                } catch (\Throwable $__e) {
                    $userClients = [];
                    $userProjects = [];
                }

                // 「その他」クライアント・プロジェクトを確保し、リストに追加
                $otherClientId = null;
                $otherProjectId = null;
                try {
                    $otherClient = \App\Models\Client::firstOrCreate(
                        ['name' => 'その他', 'company_id' => null],
                        ['notes' => 'デフォルト「その他」クライアント']
                    );
                    $otherProject = \App\Models\ProjectJob::firstOrCreate(
                        ['title' => 'その他', 'client_id' => $otherClient->id],
                        ['detail' => 'デフォルト「その他」案件']
                    );
                    $otherClientId = $otherClient->id;
                    $otherProjectId = $otherProject->id;
                    $userClients = collect($userClients);
                    if (!$userClients->contains('id', $otherClientId)) {
                        $userClients = $userClients->push(['id' => $otherClientId, 'name' => 'その他']);
                    }
                    $userProjects = collect($userProjects);
                    if (!$userProjects->contains('id', $otherProjectId)) {
                        $userProjects = $userProjects->push(['id' => $otherProjectId, 'title' => 'その他', 'client_id' => $otherClientId]);
                    }
                    $userClients = $userClients->values();
                    $userProjects = $userProjects->values();
                } catch (\Throwable $__e) {
                    // その他確保失敗は無視
                }

                $members = [];
                try {
                    $memberUserIds = array_filter(array_unique([
                        $user ? $user->id : null,
                        $assignment ? $assignment->user_id : null,
                    ]));
                    $members = \App\Models\User::whereIn('id', array_values($memberUserIds))
                        ->get(['id', 'name'])
                        ->toArray();
                } catch (\Throwable $__e) {
                    if ($user) $members = [['id' => $user->id, 'name' => $user->name]];
                }

                $company = null;
                $department = null;
                try {
                    if ($user && isset($user->company_id) && $user->company_id) $company = \App\Models\Company::find($user->company_id);
                    if ($user && isset($user->department_id) && $user->department_id) $department = \App\Models\Department::find($user->department_id);
                } catch (\Throwable $__e) {
                    $company = null;
                    $department = null;
                }

                $types = [];
                $sizes = [];
                $stages = [];
                $statuses = [];
                $difficulties = [];
                try {
                    $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
                    $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
                    $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
                    $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
                    $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);
                } catch (\Throwable $__e) {
                    $types = [];
                    $sizes = [];
                    $stages = [];
                    $statuses = [];
                    $difficulties = [];
                }

                return Inertia::render('JobBox/Edit_User', [
                    'event' => $event,
                    'projectJobAssignment' => $assignment,
                    'userClients' => $userClients,
                    'userProjects' => $userProjects,
                    'otherClientId' => $otherClientId,
                    'otherProjectId' => $otherProjectId,
                    'members' => $members,
                    'company' => $company,
                    'department' => $department,
                    'types' => $types,
                    'sizes' => $sizes,
                    'stages' => $stages,
                    'statuses' => $statuses,
                    'difficulties' => $difficulties,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('EventController::edit failed', ['error' => $e->getMessage()]);
        }
        return Inertia::render('Events/Edit', ['event' => $event]);
    }

    // ──────────────────────────────────────────────────────────────────
    //  proof_schedule 自動連動ヘルパー
    //  event が proof_request に紐づく assignment を持つ場合、
    //  proof_schedules に upsert する
    // ──────────────────────────────────────────────────────────────────
    private function syncProofScheduleFromEvent(Event $event): void
    {
        try {
            if (! Schema::hasTable('proof_schedules') || ! Schema::hasTable('proof_requests')) {
                return;
            }
            if (! $event->project_job_assignment_id || ! $event->starts_at || ! $event->ends_at) {
                return;
            }

            // ① pja_operator 直結の場合（ProofRequest.project_job_assignment_id = pja_operator.id）
            $proofRequest = \App\Models\ProofRequest::where('project_job_assignment_id', $event->project_job_assignment_id)
                ->whereNotIn('status', ['completed'])
                ->first();

            // ② pja101（自己割当の校正スロット）経由の場合
            //    event.project_job_assignment_id = pja101.id
            //    pja101.coordinator_assignment_id = pja100.id
            //    → project_job_id + proofreader_id で ProofRequest を特定
            if (! $proofRequest) {
                $pja = \App\Models\ProjectJobAssignment::find($event->project_job_assignment_id);
                if ($pja && $pja->job_type === 'proof' && $pja->sender_id === $pja->user_id) {
                    $proofRequest = \App\Models\ProofRequest::where('project_job_id', $pja->project_job_id)
                        ->where('proofreader_id', $pja->user_id)
                        ->whereNotIn('status', ['completed'])
                        ->latest()
                        ->first();
                }
            }

            if (! $proofRequest) {
                return;
            }

            \App\Models\ProofSchedule::updateOrCreate(
                [
                    'proof_request_id' => $proofRequest->id,
                    'user_id'          => $event->user_id,
                    'event_id'         => $event->id,
                ],
                [
                    'starts_at' => $event->starts_at,
                    'ends_at'   => $event->ends_at,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('EventController: syncProofScheduleFromEvent failed', [
                'error'    => $e->getMessage(),
                'event_id' => $event->id ?? null,
            ]);
        }
    }

}
