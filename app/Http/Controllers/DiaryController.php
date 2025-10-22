<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use Intervention\Image\ImageManager;
use App\Models\Diary;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Services\AttachmentService;

class DiaryController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        // server-side filters and pagination for personal diaries
        $days = intval($request->input('days', 7));
        $perPage = intval($request->input('perPage', 30));
        $page = max(1, intval($request->input('page', 1)));
        $q = trim((string) $request->input('q', ''));
        $unread = intval($request->input('unread', 0));
        $onlyDate = $request->input('date', null);

        // if a specific date is requested, return that date's entries (full content)
        if ($onlyDate) {
            $diaries = Diary::where('user_id', $userId)
                ->where('date', $onlyDate)
                ->orderBy('date', 'desc')
                ->get();

            $diariesArr = $diaries->map(function ($d) {
                return [
                    'id' => $d->id,
                    'content' => $d->content ?? '',
                    'description' => strip_tags($d->content ?? ''),
                    'date' => $d->date->toDateString(),
                ];
            })->values();

            $filters = ['date' => $onlyDate, 'fullContent' => true, 'unread' => $unread];

            return Inertia::render('Diaries/Index', [
                'diaries' => $diariesArr,
                'meta' => null,
                'filters' => $filters,
            ]);
        }

        // free-text query -> diary-level pagination
        if ($q !== '') {
            $lower = now()->subDays($days)->toDateString();
            $upper = now()->toDateString();

            $query = Diary::where('user_id', $userId)
                ->where('date', '>=', $lower)
                ->where('date', '<=', $upper)
                ->where(function ($qq) use ($q) {
                    if (is_numeric($q)) {
                        $qq->orWhere('id', intval($q));
                    }
                    $qq->orWhere('content', 'like', '%' . $q . '%');
                });

            if ($unread) {
                $query->whereRaw("JSON_CONTAINS(read_by, JSON_ARRAY(?)) = 0", [$userId]);
            }

            $paginator = $query->orderBy('date', 'desc')->paginate($perPage)->withQueryString();
            $collection = $paginator->getCollection();

            $diariesArr = $collection->map(function ($d) {
                return [
                    'id' => $d->id,
                    'content' => $d->content ?? '',
                    'description' => strip_tags($d->content ?? ''),
                    'date' => $d->date->toDateString(),
                ];
            })->values();

            $meta = $paginator->toArray()['meta'] ?? null;
            $filters = ['q' => $q, 'days' => $days, 'perPage' => $perPage, 'unread' => $unread];

            return Inertia::render('Diaries/Index', [
                'diaries' => $diariesArr,
                'meta' => $meta,
                'filters' => $filters,
            ]);
        }

        // default: return one diary per distinct date within the days window, paginated by date
        $lower = now()->subDays($days)->toDateString();
        $upper = now()->toDateString();

        $datesQuery = Diary::where('user_id', $userId)
            ->where('date', '>=', $lower)
            ->where('date', '<=', $upper);

        if ($unread) {
            $datesQuery->whereRaw("JSON_CONTAINS(read_by, JSON_ARRAY(?)) = 0", [$userId]);
        }

        $dates = $datesQuery->orderBy('date', 'desc')
            ->distinct()
            ->pluck('date')
            ->map(function ($d) {
                // ensure we have plain date strings, not Carbon objects
                try {
                    return is_object($d) && method_exists($d, 'toDateString') ? $d->toDateString() : (string) $d;
                } catch (\Throwable $_) {
                    return (string) $d;
                }
            })->toArray();

        $totalDates = count($dates);
        $sliced = array_slice($dates, ($page - 1) * $perPage, $perPage);

        $diariesQuery = Diary::where('user_id', $userId)
            ->whereIn('date', $sliced)
            ->orderBy('date', 'desc');

        if ($unread) {
            $diariesQuery->whereRaw("JSON_CONTAINS(read_by, JSON_ARRAY(?)) = 0", [$userId]);
        }

        $diaries = $diariesQuery->get();

        // pick the latest diary per date
        $grouped = $diaries->groupBy(function ($d) {
            return $d->date->toDateString();
        });

        $diariesArr = [];
        foreach ($sliced as $dateKey) {
            $list = $grouped->get($dateKey, collect());
            if ($list->count() === 0) continue;
            // choose the latest created_at record for that date
            $latest = $list->sortByDesc('created_at')->first();
            $diariesArr[] = [
                'id' => $latest->id,
                'content' => $latest->content ?? '',
                'description' => strip_tags($latest->content ?? ''),
                'date' => $latest->date->toDateString(),
            ];
        }

        $lastPage = (int) ceil($totalDates / max(1, $perPage));
        $meta = [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $totalDates,
        ];

        $filters = ['q' => '', 'days' => $days, 'perPage' => $perPage, 'unread' => $unread];

        // end debugging

        return Inertia::render('Diaries/Index', [
            'diaries' => collect($diariesArr),
            'meta' => $meta,
            'filters' => $filters,
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
        // pre-save debug logging removed
        $diary->save();

        // 本文内の [[attachment:{id}:filename]] プレースホルダを検出し、該当する attachments レコードを日報に紐付ける
        $contentForScan = $data['content'] ?? $request->input('content', '');
        if ($contentForScan) {
            preg_match_all('/\[\[attachment:(\d+):[^\]]+\]\]/', $contentForScan, $matches);
            if (!empty($matches[1])) {
                $ids = array_map('intval', $matches[1]);
                $attachments = Attachment::whereIn('id', $ids)->get();
                $now = now();
                $toInsert = [];
                foreach ($attachments as $a) {
                    $toInsert[] = [
                        'attachment_id' => $a->id,
                        'attachable_type' => \App\Models\Diary::class,
                        'attachable_id' => $diary->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (!empty($toInsert)) {
                    foreach (array_chunk($toInsert, 200) as $chunk) {
                        DB::table('attachmentables')->insertOrIgnore($chunk);
                    }
                }
            }
        }

        // 添付ファイル保存: AttachmentService に処理を委譲してサムネイル生成/DB登録/ピボットを集中管理
        if ($request->hasFile('files')) {
            $svc = new AttachmentService();
            foreach ($request->file('files') as $file) {
                try {
                    // storeUploadedFile will save to public/attachments, create thumbnail when applicable,
                    // create Attachment DB row and attach pivot to the Diary when attachable args are provided.
                    $svc->storeUploadedFile($file, Auth::user(), \App\Models\Diary::class, $diary->id);
                } catch (\Throwable $__e) {
                    Log::warning('DiaryController: AttachmentService storeUploadedFile failed: ' . $__e->getMessage());
                }
            }
        }

        return redirect()->route('diaries.index');
    }

    public function show(Diary $diary)
    {
        $this->authorize('view', $diary);
        // Load relations and normalize read_by / comments to match interactions view
        $diary->load('user', 'comments', 'attachments');

        $readBy = $diary->read_by ?? [];
        $readByNames = [];
        $readByStructured = [];
        if (!empty($readBy) && is_array($readBy)) {
            $names = User::whereIn('id', $readBy)->pluck('name', 'id')->toArray();
            $readByNames = array_map(function ($id) use ($names) {
                return $names[$id] ?? ('ID:' . $id);
            }, $readBy);

            $readByStructured = array_map(function ($id) use ($names) {
                return [
                    'id' => $id,
                    'name' => $names[$id] ?? ('ID:' . $id),
                ];
            }, $readBy);
        }

        $diaryArray = $diary->toArray();
        $diaryArray['read_by_names'] = $readByNames;
        $diaryArray['read_by'] = $readByStructured;
        $diaryArray['comments'] = array_map(function ($c) {
            return [
                'id' => $c['id'] ?? null,
                'user_id' => $c['user_id'] ?? null,
                'user_name' => $c['user_name'] ?? null,
                'comment' => $c['comment'] ?? '',
                'created_at' => $c['created_at'] ?? null,
            ];
        }, $diaryArray['comments'] ?? []);

        // Map attachments to expose signed urls and thumbnail urls when available
        $diaryArray['attachments'] = $diary->attachments->map(function ($att) {
            $url = null;
            $public = null;
            $thumb = null;
            if (($att->status === 'ready' || $att->status === 1) && $att->path) {
                try {
                    $url = URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $att->path]);
                } catch (\Throwable $__e) {
                    try {
                        $url = route('api.attachments.stream', ['path' => $att->path]);
                    } catch (\Throwable $_e) {
                        $url = null;
                    }
                }
                $public = $att->path ? asset('storage/' . ltrim($att->path, '/')) : null;

                // thumbnail candidate(s)
                $candidate = dirname($att->path) . '/thumbs/' . basename($att->path);
                if (Storage::disk('public')->exists($candidate)) {
                    try {
                        $thumb = URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $candidate]);
                    } catch (\Throwable $_e) {
                        $thumb = asset('storage/' . ltrim($candidate, '/'));
                    }
                } else {
                    $alt = 'attachments/thumbs/' . basename($att->path);
                    if (Storage::disk('public')->exists($alt)) {
                        try {
                            $thumb = URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $alt]);
                        } catch (\Throwable $_e) {
                            $thumb = asset('storage/' . ltrim($alt, '/'));
                        }
                    }
                }
            }
            return [
                'id' => $att->id,
                'original_name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'size' => $att->size,
                'status' => $att->status,
                'url' => $url,
                'public_url' => $public,
                'path' => $att->path,
                'thumb_url' => $thumb,
            ];
        })->values();

        return Inertia::render('Diaries/Show', [
            'diary' => $diaryArray,
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
                    $attachments = Attachment::whereIn('id', $ids)->get();
                    $now = now();
                    $toInsert = [];
                    foreach ($attachments as $a) {
                        $toInsert[] = [
                            'attachment_id' => $a->id,
                            'attachable_type' => \App\Models\Diary::class,
                            'attachable_id' => $diary->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($toInsert)) {
                        foreach (array_chunk($toInsert, 200) as $chunk) {
                            DB::table('attachmentables')->insertOrIgnore($chunk);
                        }
                    }
                }
            }

            // 添付ファイル保存（追加分のみ）
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $isImage = strpos($file->getMimeType(), 'image') === 0;
                    $ext = $file->getClientOriginalExtension();
                    $origName = basename($file->getClientOriginalName());
                    $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $origName);
                    $unique = uniqid() . '_' . $safeName;
                    $path = 'attachments/' . $unique;

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
                        // create thumbnail for diary images
                        try {
                            if (class_exists(\Intervention\Image\ImageManager::class)) {
                                if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
                                    $tManager = ImageManager::imagick();
                                } else {
                                    $tManager = ImageManager::gd();
                                }
                                $tImg = $tManager->read($file);
                                // Ensure thumbnail fits within 400x400 while preserving aspect ratio
                                try {
                                    if ($tImg->width() > 400) {
                                        $tImg->resize(400, null, function ($constraint) {
                                            $constraint->aspectRatio();
                                            $constraint->upsize();
                                        });
                                    }
                                } catch (\Throwable $_resizeEx) {
                                    // ignore resizing errors and continue
                                }
                                $thumbPath = 'attachments/thumbs/' . basename($path);
                                $thumbEncoder = new \Intervention\Image\Encoders\JpegEncoder(80);
                                $thumbEncoded = $tImg->encode($thumbEncoder);
                                $thumbBin = (string) $thumbEncoded->toDataUri() ? base64_decode(preg_replace('#^data:.*?;base64,#', '', $thumbEncoded->toDataUri())) : (string) $thumbEncoded;
                                Storage::disk('public')->put($thumbPath, $thumbBin);
                                try {
                                    Storage::disk('public')->setVisibility($thumbPath, 'public');
                                    $realThumb = Storage::disk('public')->path($thumbPath) ?? null;
                                    if ($realThumb && file_exists($realThumb)) {
                                        @chmod($realThumb, 0644);
                                    }
                                } catch (\Throwable $_exPerm) {
                                    logger()->warning('DiaryController: could not set permissions for thumb', ['thumb' => $thumbPath, 'error' => $_exPerm->getMessage()]);
                                }
                            }
                        } catch (\Throwable $_t) {
                            Log::warning('Diary thumbnail create failed: ' . $_t->getMessage());
                        }
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
                        Storage::disk('public')->putFileAs('attachments', $file, $unique);
                    }
                    $attachment = \App\Models\Attachment::create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                    ]);
                    try {
                        $diary->attachments()->attach($attachment->id, ['created_at' => now(), 'updated_at' => now()]);
                    } catch (\Throwable $_ex) {
                        logger()->warning('DiaryController: could not attach uploaded attachment to diary', ['attachment_id' => $attachment->id, 'diary_id' => $diary->id, 'error' => $_ex->getMessage()]);
                    }
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

        // 添付ファイル保存（追加分のみ）: AttachmentService に委譲
        if ($request->hasFile('files')) {
            $svc = new AttachmentService();
            foreach ($request->file('files') as $file) {
                try {
                    $svc->storeUploadedFile($file, Auth::user(), \App\Models\Diary::class, $diary->id);
                } catch (\Throwable $__e) {
                    Log::warning('DiaryController (update): AttachmentService storeUploadedFile failed: ' . $__e->getMessage());
                }
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
