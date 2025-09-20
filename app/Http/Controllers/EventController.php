<?php

namespace App\Http\Controllers;

use App\Models\Event;
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
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Events\MessageCreated;
use App\Services\HtmlSanitizer;
use App\Models\Team;
use App\Models\Unit;

class EventController extends Controller
{
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

        // leader check and gather users from teams
        $isLeader = Team::where('leader_id', $currentUser->id)->whereIn('team_type', ['department', 'unit'])->exists();
        if ($isLeader) {
            $teams = Team::where('leader_id', $currentUser->id)
                ->whereIn('team_type', ['department', 'unit'])
                ->get();

            foreach ($teams as $team) {
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
        }

        return array_values(array_unique(array_filter($userIds)));
    }
    /**
     * カレンダーからのリサイズ用: 時間のみバリデート・更新
     */
    public function update_from_calendar(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'startHour' => ['required', 'regex:/^\\d{2}$/'],
            'startMinute' => ['required', 'regex:/^\\d{2}$/'],
            'endHour' => ['required', 'regex:/^\\d{2}$/'],
            'endMinute' => ['required', 'regex:/^\\d{2}$/'],
        ]);

        // 日付と時刻を結合
        $start = $validated['date'] . ' ' . $validated['startHour'] . ':' . $validated['startMinute'] . ':00';
        $end = $validated['date'] . ' ' . $validated['endHour'] . ':' . $validated['endMinute'] . ':00';

        $event->start = $start;
        $event->end = $end;
        $event->save();

        return response()->json(['message' => 'Event time updated successfully.']);
    }


    // ユーザーの予定一覧取得（カレンダー表示用）
    public function index()
    {
        $date = request('date');
        $requestedUserId = request('user_id');

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
        if ($date) {
            // Interpret the incoming date (YYYY-MM-DD) in the application's timezone
            // (config('app.timezone')) and convert to UTC range so we can robustly
            // compare against timestamp columns stored in UTC in the DB.
            try {
                $tz = config('app.timezone') ?: 'UTC';
                $startOfDay = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
                $endOfDay = Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay();
                // convert to UTC for DB comparison
                $startUtc = $startOfDay->copy()->setTimezone('UTC');
                $endUtc = $endOfDay->copy()->setTimezone('UTC');
            } catch (\Exception $e) {
                // fallback to naive whereDate if parsing fails
                if (Schema::hasColumn('events', 'starts_at')) {
                    $query->whereDate('starts_at', $date);
                } else {
                    $query->whereDate('start', $date);
                }
                $startUtc = null;
                $endUtc = null;
            }

            if (isset($startUtc) && isset($endUtc)) {
                // Build both UTC-range and local app-tz range strings. Some rows may have been
                // persisted in UTC while others in app timezone; include either via OR so we
                // don't accidentally drop events.
                $utcStartStr = $startUtc->toDateTimeString();
                $utcEndStr = $endUtc->toDateTimeString();
                $localStartStr = $startOfDay->toDateTimeString();
                $localEndStr = $endOfDay->toDateTimeString();

                if (Schema::hasColumn('events', 'starts_at')) {
                    $query->where(function ($q) use ($utcStartStr, $utcEndStr, $localStartStr, $localEndStr) {
                        $q->whereBetween('starts_at', [$utcStartStr, $utcEndStr])
                            ->orWhereBetween('starts_at', [$localStartStr, $localEndStr]);
                    });
                } else {
                    $query->where(function ($q) use ($utcStartStr, $utcEndStr, $localStartStr, $localEndStr) {
                        $q->whereBetween('start', [$utcStartStr, $utcEndStr])
                            ->orWhereBetween('start', [$localStartStr, $localEndStr]);
                    });
                }
                try {
                    Log::debug('EventController@index using range strings', ['date' => $date, 'utc' => [$utcStartStr, $utcEndStr], 'local' => [$localStartStr, $localEndStr]]);
                } catch (\Throwable $__e) {
                }
            }
        }
        $events = $query->get();
        try {
            // Debug: log which events were fetched for this request (id, start, end)
            $summ = $events->map(function ($e) {
                return [
                    'id' => $e->id ?? null,
                    'start' => $e->start ?? ($e->starts_at ?? null),
                    'end' => $e->end ?? ($e->ends_at ?? null),
                ];
            })->toArray();
            Log::debug('EventController@index fetched events', ['date' => $date, 'count' => $events->count(), 'events' => $summ]);
        } catch (\Throwable $__logE) {
            // ignore logging errors
        }
        return response()->json($events);
    }

    // 予定の新規作成
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (trim(strip_tags($value)) === '') {
                        $fail('説明を入力してください。');
                    }
                }
            ],
            'startHour' => 'required',
            'startMinute' => 'required',
            'endHour' => 'required',
            'endMinute' => 'required',
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
                $existingAssignment = ProjectJobAssignment::find($jobId);
                if ($existingAssignment) {
                    if (Schema::hasColumn('project_job_assignments', 'scheduled') && $existingAssignment->scheduled) {
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

        // If this event was created for a job assignment, mark that assignment as scheduled.
        $jobId = $request->input('job_id');
        if ($jobId) {
            try {
                $assignment = ProjectJobAssignment::find($jobId);
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
                        if (Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
                            $assignment->scheduled_at = $event->start;
                        }
                        // If there's a boolean scheduled flag, set it true
                        if (Schema::hasColumn('project_job_assignments', 'scheduled')) {
                            $assignment->scheduled = true;
                        }
                        // If the assignments table keeps a separate `date` column, and it's empty,
                        // populate it with the event's date portion so calendar-based flows have a date.
                        if (Schema::hasColumn('project_job_assignments', 'date') && empty($assignment->date)) {
                            $assignment->date = date('Y-m-d', strtotime($event->start));
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
                \App\Models\Attachment::create([
                    'event_id' => $event->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        return redirect()->route('calendar.index');
    }

    // 予定の更新
    public function update(Request $request, Event $event)
    {
        Log::debug('Event update request', $request->all());
        $this->authorize('update', $event);
        Log::debug('Request all: ' . json_encode($request->all(), JSON_UNESCAPED_UNICODE));
        Log::debug('Request input: ' . json_encode($request->input(), JSON_UNESCAPED_UNICODE));
        // $request->get()は引数必須のため削除
        $data = $request->validate([
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (trim(strip_tags($value)) === '') {
                        $fail('説明を入力してください。');
                    }
                }
            ],
            'startHour' => 'required',
            'startMinute' => 'required',
            'endHour' => 'required',
            'endMinute' => 'required',
        ]);
        Log::debug('Validated data', $data);
        $data['description'] = $request->input('description', '');
        $data['user_id'] = Auth::id();
        $data['start'] = date('Y-m-d H:i:00', strtotime($data['date'] . ' ' . $data['startHour'] . ':' . $data['startMinute']));
        $data['end'] = date('Y-m-d H:i:00', strtotime($data['date'] . ' ' . $data['endHour'] . ':' . $data['endMinute']));
        // Ensure we only include `date` if the column exists; otherwise remove it to avoid SQL errors.
        if (Schema::hasColumn('events', 'date')) {
            $data['date'] = $data['date'] ?? date('Y-m-d', strtotime($data['start']));
        } else {
            if (array_key_exists('date', $data)) {
                unset($data['date']);
            }
        }
        Log::debug('Start/End generated', ['start' => $data['start'], 'end' => $data['end']]);
        Log::debug('Event before update', $event->toArray());
        $event->update($data);
        Log::debug('Event after update', $event->fresh()->toArray());

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
                \App\Models\Attachment::create([
                    'event_id' => $event->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }
        Log::debug('Event update finished');
        return redirect()->route('calendar.index');
    }

    // 予定の削除
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        // 添付ファイルも削除
        foreach ($event->attachments as $attachment) {
            if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                Storage::disk('public')->delete($attachment->path);
            }
            $attachment->delete();
        }
        $event->delete();
        // If this request came from Inertia, return a redirect (Inertia expects a redirect/Inertia response).
        if (request()->header('X-Inertia')) {
            return redirect()->route('calendar.index');
        }
        return response()->json(['message' => 'deleted']);
    }

    // イベント詳細表示
    public function show(Event $event)
    {
        // 添付ファイルも取得する場合はリレーションをロード
        $event->load('attachments');
        // if this event is linked to a project job assignment, eager-load that assignment
        if (Schema::hasColumn('events', 'project_job_assignment_id') && $event->project_job_assignment_id) {
            $event->load('projectJobAssignment.projectJob.client');
        }
        $hideEdit = request()->query('hide_edit') ? true : false;
        return Inertia::render('Events/Show', [
            'event' => $event,
            'hide_edit' => $hideEdit,
        ]);
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

        try {
            DB::beginTransaction();

            $assignment = ProjectJobAssignment::find($event->project_job_assignment_id);
            if (!$assignment) {
                DB::rollBack();
                return redirect()->back()->with('error', '関連する割り当てが見つかりません。');
            }

            // mark assignment completed if column exists
            if (Schema::hasColumn('project_job_assignments', 'completed')) {
                $assignment->completed = true;
            }
            $assignment->save();

            // update any JobAssignmentMessage rows to reflect completed state
            try {
                if (Schema::hasColumn('job_assignment_messages', 'completed')) {
                    \App\Models\JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)
                        ->update(['completed' => true]);
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }

            // Prefix event title with completion marker for persistent visibility
            $prefix = '【完了】';
            if (strpos($event->title, $prefix) !== 0) {
                $event->title = $prefix . $event->title;
                $event->save();
            }

            // Create an internal Message to notify assigner/coordinators or emit a toast
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
        $date = $request->query('date', now()->toDateString());
        $jobId = $request->query('job');
        $jobData = null;
        if ($jobId) {
            // load lookup relations so Create page can prefill human-friendly labels
            $assignment = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'projectJob', 'user', 'size', 'stage', 'workItemType', 'statusModel'])->find($jobId);
            if ($assignment) {
                // Use model helper to produce consistent prefill data
                $jobData = $assignment->toEventPrefill();
            }
        }
        // Debug logging to ensure jobData is created and query params are received
        try {
            Log::debug('EventController::create query', ['query' => $request->query()]);
            Log::debug('EventController::create jobId', ['jobId' => $jobId]);
            if (!empty($jobData)) {
                Log::debug('EventController::create jobData', ['jobData' => $jobData]);
            } else {
                Log::debug('EventController::create jobData: null');
            }
        } catch (\Throwable $_e) {
            // ignore logging errors
        }
        return Inertia::render('Events/Create', [
            'date' => $date,
            'job' => $jobData,
        ]);
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
        Log::debug('EditController event', $event->toArray());
        return Inertia::render('Events/Edit', [
            'event' => $event,
        ]);
    }
}
