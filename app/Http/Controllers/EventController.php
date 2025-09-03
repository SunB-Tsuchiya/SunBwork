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
use Intervention\Image\ImageManager;
use Inertia\Inertia;
use App\Models\ProjectJobAssignment;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Events\MessageCreated;
use App\Services\HtmlSanitizer;

class EventController extends Controller
{
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
        $query = Event::where('user_id', Auth::id());
        if ($date) {
            $query->whereDate('start', $date);
        }
        $events = $query->get();
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
        $event = new Event();
        $event->user_id = Auth::id();
        $event->title = $data['title'];
        $event->description = $data['description'];
        $event->start = $data['start'];
        $event->end = $data['end'];
        $event->date = $data['date'];
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
                        $assignment->save();
                    });

                    // After marking scheduled, send a notification message to the assigner (if identifiable)
                    try {
                        // ensure projectJob relation is loaded
                        $assignment->load('projectJob');
                        $assignerId = null;
                        // prefer explicit creator column if exists
                        if (Schema::hasColumn('project_job_assignments', 'created_by') && isset($assignment->created_by)) {
                            $assignerId = $assignment->created_by;
                        }
                        // fallback to project job owner
                        if (!$assignerId && $assignment->projectJob && isset($assignment->projectJob->user_id)) {
                            $assignerId = $assignment->projectJob->user_id;
                        }

                        if ($assignerId) {
                            $sanitizer = app(HtmlSanitizer::class);
                            // Build message using the same labels as sendTestCompletion so frontend formatting applies
                            $bodyLines = [];
                            $bodyLines[] = "ジョブ割り当て終了のご連絡";
                            $bodyLines[] = "プロジェクトジョブID: " . ($assignment->project_job_id ?? '-');
                            $bodyLines[] = "予定をセットしたユーザーID: " . (Auth::id() ?? '-');
                            $bodyLines[] = "イベント名: " . ($event->title ?? ($assignment->title ?: ($assignment->projectJob->name ?? '-')));
                            $bodyLines[] = "開始: " . ($event->start ?? '-');
                            $bodyLines[] = "終了: " . ($event->end ?? '-');
                            // prefer assignment detail then project job detail
                            $detailText = $assignment->detail ?? ($assignment->projectJob->detail ?? '');
                            $bodyLines[] = "詳細: " . ($detailText ?: '');

                            $body = implode("\n", $bodyLines);
                            $clean = $sanitizer->purify($body);

                            try {
                                Log::info('EventController: creating Message payload', ['from_user_id' => Auth::id(), 'subject' => 'ジョブ割り当て終了', 'assignerId' => $assignerId]);
                                $message = Message::create([
                                    'from_user_id' => Auth::id(),
                                    'subject' => 'ジョブ割り当て終了',
                                    'body' => $clean,
                                    'status' => 'sent',
                                    'sent_at' => now(),
                                ]);
                                Log::info('EventController: Message created', ['message_id' => $message->id]);

                                $recipient = MessageRecipient::create([
                                    'message_id' => $message->id,
                                    'user_id' => $assignerId,
                                    'type' => 'to',
                                ]);
                                Log::info('EventController: MessageRecipient created', ['recipient_id' => $recipient->id ?? null, 'message_id' => $message->id, 'assignerId' => $assignerId]);

                                try {
                                    $message->load('recipients');
                                    event(new MessageCreated($message));
                                    Log::info('EventController: MessageCreated event fired', ['message_id' => $message->id]);
                                } catch (\Throwable $__e) {
                                    Log::warning('EventController: broadcast MessageCreated failed', ['error' => $__e->getMessage()]);
                                }
                            } catch (\Throwable $__e) {
                                Log::error('EventController: failed to create Message/Recipient', ['error' => $__e->getMessage()]);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send set-complete message', ['error' => $e->getMessage()]);
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
        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }

    // イベント新規作成画面表示
    public function create(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $jobId = $request->query('job');
        $jobData = null;
        if ($jobId) {
            $assignment = \App\Models\ProjectJobAssignment::with(['projectJob', 'user'])->find($jobId);
            if ($assignment) {
                $jobData = [
                    'id' => $assignment->id,
                    'project_job_id' => $assignment->project_job_id,
                    'title' => $assignment->title ?: ($assignment->projectJob ? $assignment->projectJob->name : null),
                    // details from assignment first, fallback to projectJob detail
                    'details' => $assignment->detail ?? ($assignment->projectJob ? $assignment->projectJob->detail : null),
                    // include assigned user name if available
                    'assigned_user_name' => $assignment->user ? $assignment->user->name : null,
                    // difficulty
                    'difficulty' => $assignment->difficulty ?? ($assignment->projectJob ? $assignment->projectJob->difficulty : null),
                    // desired/ preferred dates and time
                    'desired_start_date' => $assignment->desired_start_date ? (method_exists($assignment->desired_start_date, 'format') ? $assignment->desired_start_date->format('Y-m-d') : (string) $assignment->desired_start_date) : null,
                    'desired_end_date' => $assignment->desired_end_date ? (method_exists($assignment->desired_end_date, 'format') ? $assignment->desired_end_date->format('Y-m-d') : (string) $assignment->desired_end_date) : null,
                    'desired_time' => $assignment->desired_time ?? null,
                    // keep backward-compatible preferred_date
                    'preferred_date' => $assignment->desired_start_date ? (method_exists($assignment->desired_start_date, 'format') ? $assignment->desired_start_date->format('Y-m-d') : (string) $assignment->desired_start_date) : null,
                ];
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
