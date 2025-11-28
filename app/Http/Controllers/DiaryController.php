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

        // Map attachments to expose signed/stream urls and thumbnail urls using AttachmentService
        $svc = new AttachmentService();
        $diaryArray['attachments'] = $diary->attachments->map(function ($att) use ($svc) {
            $meta = [
                'original_name' => $att->original_name,
                'mime' => $att->mime_type ?? null,
                'size' => $att->size ?? null,
                'path' => $att->path,
                'attachment_id' => $att->id,
            ];
            $formatted = $svc->formatResponseMeta($meta);
            // try to provide a signed stream URL for app-internal use when possible
            try {
                if (!empty($formatted['path'])) {
                    $formatted['url'] = URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $formatted['path']]);
                }
            } catch (\Throwable $_e) {
                try {
                    if (!empty($formatted['path'])) $formatted['url'] = route('attachments.stream', ['path' => $formatted['path']]);
                } catch (\Throwable $__e) {
                    // leave formatted['url'] as-is
                }
            }
            // attempt to populate thumb_url if thumb_path exists or common thumb location exists
            if (empty($formatted['thumb_url']) && !empty($formatted['path'])) {
                $candidate = dirname($formatted['path']) . '/thumbs/' . basename($formatted['path']);
                if (Storage::disk('public')->exists($candidate)) {
                    try {
                        $formatted['thumb_url'] = URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $candidate]);
                    } catch (\Throwable $_te) {
                        $formatted['thumb_url'] = asset('storage/' . ltrim($candidate, '/'));
                    }
                } else {
                    $alt = 'attachments/thumbs/' . basename($formatted['path']);
                    if (Storage::disk('public')->exists($alt)) {
                        try {
                            $formatted['thumb_url'] = URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $alt]);
                        } catch (\Throwable $_te) {
                            $formatted['thumb_url'] = asset('storage/' . ltrim($alt, '/'));
                        }
                    }
                }
            }
            return $formatted + ['id' => $att->id, 'status' => $att->status];
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

            // 添付ファイル保存（追加分のみ）: AttachmentService に委譲 (画像処理・サムネイル・DB登録・ピボットを統一)
            if ($request->hasFile('files')) {
                $svc = new AttachmentService();
                foreach ($request->file('files') as $file) {
                    try {
                        $svc->storeUploadedFile($file, Auth::user(), \App\Models\Diary::class, $diary->id);
                    } catch (\Throwable $__e) {
                        Log::warning('DiaryController: AttachmentService storeUploadedFile failed: ' . $__e->getMessage());
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
