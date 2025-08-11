<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;
use Inertia\Inertia;

class EventController extends Controller
{
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
                    $img = \Intervention\Image\Facades\Image::make($file);
                    if ($img->width() > 1200) {
                        $img->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    $img->encode($ext, 80);
                    \Storage::disk('public')->put($path, $img);
                } else {
                    \Storage::disk('public')->putFileAs('event_attachments', $file, $uniqueName);
                }
                \App\Models\Attachment::create([
                    'event_id' => $event->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        return redirect()->route('dashboard');
    }

    // 予定の更新
    public function update(Request $request, Event $event)
    {
        
    \Log::debug('Event update request', $request->all());
    $this->authorize('update', $event);
    \Log::debug('Request all: ' . json_encode($request->all(), JSON_UNESCAPED_UNICODE));
    \Log::debug('Request input: ' . json_encode($request->input(), JSON_UNESCAPED_UNICODE));
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
        \Log::debug('Validated data', $data);
        $data['description'] = $request->input('description', '');
        $data['user_id'] = Auth::id();
        $data['start'] = date('Y-m-d H:i:00', strtotime($data['date'] . ' ' . $data['startHour'] . ':' . $data['startMinute']));
        $data['end'] = date('Y-m-d H:i:00', strtotime($data['date'] . ' ' . $data['endHour'] . ':' . $data['endMinute']));
        \Log::debug('Start/End generated', ['start' => $data['start'], 'end' => $data['end']]);
        \Log::debug('Event before update', $event->toArray());
        $event->update($data);
        \Log::debug('Event after update', $event->fresh()->toArray());

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
                    $img = \Intervention\Image\Facades\Image::make($file);
                    if ($img->width() > 1200) {
                        $img->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    $img->encode($ext, 80);
                    \Storage::disk('public')->put($path, $img);
                } else {
                    \Storage::disk('public')->putFileAs('event_attachments', $file, $uniqueName);
                }
                \App\Models\Attachment::create([
                    'event_id' => $event->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }
        \Log::debug('Event update finished');
        return redirect()->route('dashboard');
    }

    // 予定の削除
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        // 添付ファイルも削除
        foreach ($event->attachments as $attachment) {
            if ($attachment->path && \Storage::disk('public')->exists($attachment->path)) {
                \Storage::disk('public')->delete($attachment->path);
            }
            $attachment->delete();
        }
        $event->delete();
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
        return Inertia::render('Events/Create', [
            'date' => $date,
        ]);
    }

    // イベント編集画面表示
    public function edit(Event $event)
    {
        $event->date = \Carbon\Carbon::parse($event->start)->toDateString();
        \Log::debug('EditController event', $event->toArray());
        return Inertia::render('Events/Edit', [
            'event' => $event,
        ]);
    }
}
