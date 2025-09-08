<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Intervention\Image\ImageManager;
use App\Models\Diary;
use App\Models\Attachment;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class DiaryController extends Controller
{
    public function index()
    {
        $diaries = Diary::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
        return Inertia::render('Diaries/Index', [
            'diaries' => $diaries,
        ]);
    }

    public function create(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $userId = Auth::id();
        $diary = Diary::where('user_id', $userId)->whereDate('created_at', $date)->first();
        if ($diary) {
            // 既に日報がある場合は編集画面へ
            return redirect()->route('diaries.edit', $diary->id);
        }
        return Inertia::render('Diaries/Create', [
            'date' => $date,
        ]);
    }

    public function store(Request $request)
    {

        $data = $request->validate([
            'date' => 'required|date',
            'content' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (trim(strip_tags($value)) === '') {
                        $fail('内容を入力してください。');
                    }
                }
            ],
        ]);
        // ensure diary is associated with the authenticated user
        // The schema uses timestamps rather than a dedicated `date` column.
        $createdAt = Carbon::parse($data['date'])->startOfDay();
        $diary = new Diary();
        $diary->user_id = Auth::id();
        // Persist explicit diary date (migration requires non-null date column)
        $diary->date = $createdAt->toDateString();
        $diary->content = $data['content'];
        $diary->created_at = $createdAt;
        $diary->updated_at = $createdAt;
        try {
            Log::debug('DiaryController::store - pre-save diary', ['attrs' => $diary->toArray()]);
        } catch (\Exception $_e) {
            // ignore logging errors
        }
        $diary->save();

        // 本文内の [[attachment:{id}:filename]] プレースホルダを検出し、該当する attachments レコードを日報に紐付ける
        $contentForScan = $data['content'] ?? $request->input('content', '');
        if ($contentForScan) {
            preg_match_all('/\[\[attachment:(\d+):[^\]]+\]\]/', $contentForScan, $matches);
            if (!empty($matches[1])) {
                $ids = array_map('intval', $matches[1]);
                Attachment::whereIn('id', $ids)->update(['diary_id' => $diary->id]);
            }
        }

        // 添付ファイル保存
        if ($request->hasFile('files')) {


            foreach ($request->file('files') as $file) {
                $isImage = strpos($file->getMimeType(), 'image') === 0;
                // ファイル名生成: 元のファイル名_YYYYMMDD[diary_id].[拡張子]
                $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $ext = $file->getClientOriginalExtension();
                $dateStr = date('Ymd', strtotime($diary->created_at));
                $uniqueName = $original . '_' . $dateStr . $diary->id . '.' . $ext;
                $path = 'attachments/' . $uniqueName;

                if ($isImage) {
                    // Intervention Imageでリサイズ（横幅1200px以内）
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
                        logger()->warning('DiaryController: could not set permissions for image', ['path' => $path, 'error' => $_exPerm->getMessage()]);
                    }
                } else {
                    Storage::disk('public')->putFileAs('attachments', $file, $uniqueName);
                }
                \App\Models\DiaryAttachment::create([
                    'diary_id' => $diary->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        return redirect()->route('diaries.index');
    }

    public function show(Diary $diary)
    {
        $this->authorize('view', $diary);
        return Inertia::render('Diaries/Show', [
            'diary' => $diary,
        ]);
    }

    public function edit(Diary $diary)
    {
        $this->authorize('update', $diary);
        return Inertia::render('Diaries/Edit', [
            'diary' => $diary,
        ]);
    }

    public function update(Request $request, Diary $diary)
    {
        $this->authorize('update', $diary);
        // Quick path: if the request contains `content`, treat this as the Quill-based edit
        if ($request->input('content') !== null) {
            // ensure date exists for validation: use existing diary date if absent
            $request->merge(['date' => $request->input('date', $diary->date)]);
            $data = $request->validate([
                'date' => 'required|date',
                'content' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        if (trim(strip_tags($value)) === '') {
                            $fail('内容を入力してください。');
                        }
                    }
                ],
            ]);
            $data['user_id'] = Auth::id();
            $diary->update($data);

            // 本文内の [[attachment:{id}:filename]] プレースホルダを検出し、該当する attachments レコードを日報に紐付ける
            $contentForScan = $data['content'] ?? $request->input('content', '');
            if ($contentForScan) {
                preg_match_all('/\[\[attachment:(\d+):[^\]]+\]\]/', $contentForScan, $matches);
                if (!empty($matches[1])) {
                    $ids = array_map('intval', $matches[1]);
                    Attachment::whereIn('id', $ids)->update(['diary_id' => $diary->id]);
                }
            }

            // 添付ファイル保存（追加分のみ）
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $isImage = strpos($file->getMimeType(), 'image') === 0;
                    $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $ext = $file->getClientOriginalExtension();
                    $dateStr = date('Ymd', strtotime($diary->created_at));
                    $uniqueName = $original . '_' . $dateStr . $diary->id . '.' . $ext;
                    $path = 'attachments/' . $uniqueName;

                    if ($isImage) {
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
                            logger()->warning('DiaryController: could not set permissions for image', ['path' => $path, 'error' => $_exPerm->getMessage()]);
                        }
                    } else {
                        Storage::disk('public')->putFileAs('attachments', $file, $uniqueName);
                    }
                    \App\Models\Attachment::create([
                        'diary_id' => $diary->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                    ]);
                }
            }

            return redirect()->route('diaries.index');
        }

        // fallback: existing complex update flow (kept unchanged)
        $data = $request->validate([
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (trim(strip_tags($value)) === '') {
                        $fail('内容を入力してください。');
                    }
                }
            ],
            'startHour' => 'required',
            'startMinute' => 'required',
            'endHour' => 'required',
            'endMinute' => 'required',
        ]);
        $data['description'] = $request->input('description', '');
        $data['user_id'] = Auth::id();
        $data['start'] = $data['date'] . ' ' . $data['startHour'] . ':' . $data['startMinute'] . ':00';
        $data['end'] = $data['date'] . ' ' . $data['endHour'] . ':' . $data['endMinute'] . ':00';
        $diary->update($data);

        // 添付ファイル保存（追加分のみ）
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $isImage = strpos($file->getMimeType(), 'image') === 0;
                $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $ext = $file->getClientOriginalExtension();
                $dateStr = date('Ymd', strtotime($diary->start));
                $uniqueName = $original . '_' . $dateStr . $diary->id . '.' . $ext;
                $path = 'attachments/' . $uniqueName;

                if ($isImage) {
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
                        logger()->warning('DiaryController: could not set permissions for image', ['path' => $path, 'error' => $_exPerm->getMessage()]);
                    }
                } else {
                    Storage::disk('public')->putFileAs('attachments', $file, $uniqueName);
                }
                \App\Models\Attachment::create([
                    'diary_id' => $diary->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        return redirect()->route('diaries.index');
    }

    public function destroy(Diary $diary)
    {
        $this->authorize('delete', $diary);
        // 添付ファイルも削除
        foreach ($diary->attachments as $attachment) {
            if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                Storage::disk('public')->delete($attachment->path);
            }
            $attachment->delete();
        }
        $diary->delete();
        return redirect()->route('dashboard');
    }
}
