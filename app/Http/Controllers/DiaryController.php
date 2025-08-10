<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\Diary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DiaryController extends Controller
{
    public function index()
    {
        $diaries = Diary::where('user_id', Auth::id())
            ->orderByDesc('date')
            ->get();
        return Inertia::render('Diaries/Index', [
            'diaries' => $diaries,
        ]);
    }

    public function create(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $userId = Auth::id();
        $diary = Diary::where('user_id', $userId)->where('date', $date)->first();
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
        
        $data['user_id'] = Auth::id();
        $diary = Diary::create($data);

        // 添付ファイル保存
        if ($request->hasFile('files')) {
                    

            foreach ($request->file('files') as $file) {
                $isImage = strpos($file->getMimeType(), 'image') === 0;
                // ファイル名生成: 元のファイル名_YYYYMMDD[diary_id].[拡張子]
                $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $ext = $file->getClientOriginalExtension();
                $dateStr = date('Ymd', strtotime($diary->date));
                $uniqueName = $original . '_' . $dateStr . $diary->id . '.' . $ext;
                $path = 'diary_attachments/' . $uniqueName;

                if ($isImage) {
                    // Intervention Imageでリサイズ（横幅1200px以内）
                    $img = Image::make($file);
                    if ($img->width() > 1200) {
                        $img->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    $img->encode($ext, 80); // 80%品質
                    Storage::disk('public')->put($path, $img);
                } else {
                    Storage::disk('public')->putFileAs('diary_attachments', $file, $uniqueName);
                }
                \App\Models\DiaryAttachment::create([
                    'diary_id' => $diary->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        return redirect()->route('diaries.index', $diary->id);
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
        $data = $request->validate([
            'content' => 'required',
        ]);
        $diary->update($data);
        return redirect()->route('diaries.show', $diary->id);
    }

    public function destroy(Diary $diary)
    {
        $this->authorize('delete', $diary);
        $diary->delete();
        return redirect()->route('dashboard');
    }
}
