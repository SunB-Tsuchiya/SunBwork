<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DiaryAdminController extends Controller
{
    /**
     * Show diaries for admins, grouped by department for the admin's company.
     */
    public function index(Request $request)
    {
        $admin = Auth::user();
        $companyId = $admin->company_id;

        // load users in company with their departments and all diaries (no single-date filter)
        $users = User::with('department')
            ->where('company_id', $companyId)
            ->get();

        $userIds = $users->pluck('id')->toArray();

        // server-side filters and pagination
        $days = intval($request->input('days', 30));
        $perPage = intval($request->input('perPage', 30)); // pages are number of dates per page
        $q = trim((string) $request->input('q', ''));
        $currentUserId = Auth::id();
        $page = max(1, intval($request->input('page', 1)));
        $unread = intval($request->input('unread', 0));
        $onlyDate = $request->input('date', null);

        // if a free-text query is present, fall back to diary-level pagination
        if ($q !== '') {
            $query = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->where('date', '>=', now()->subDays($days))
                ->where(function ($qq) use ($q) {
                    if (is_numeric($q)) {
                        $qq->orWhere('id', intval($q));
                    }
                    $qq->orWhere('content', 'like', '%' . $q . '%')
                        ->orWhereHas('user', function ($qu) use ($q) {
                            $qu->where('name', 'like', '%' . $q . '%');
                        });
                });

            // if unread filter requested, return diaries that do NOT contain current user in read_by
            if ($unread) {
                $query->whereRaw("JSON_SEARCH(read_by, 'one', ?) IS NULL", [$currentUserId]);
            }

            $paginator = $query->orderBy('date', 'desc')->paginate(intval($request->input('perPage', 20)))->withQueryString();
            $collection = $paginator->getCollection();

            $allReadIds = [];
            foreach ($collection as $d) {
                if (!empty($d->read_by) && is_array($d->read_by)) {
                    $allReadIds = array_merge($allReadIds, $d->read_by);
                }
            }
            $allReadIds = array_values(array_unique($allReadIds));
            $namesMap = [];
            if (!empty($allReadIds)) {
                $namesMap = User::whereIn('id', $allReadIds)->pluck('name', 'id')->toArray();
            }

            $diariesArr = $collection->map(function ($d) use ($namesMap) {
                $readBy = $d->read_by ?? [];
                $readByNames = array_map(function ($id) use ($namesMap) {
                    return $namesMap[$id] ?? ('ID:' . $id);
                }, is_array($readBy) ? $readBy : []);

                return [
                    'id' => $d->id,
                    'user_id' => $d->user_id,
                    'name' => $d->user->name ?? '',
                    'description' => mb_substr(strip_tags($d->content ?? ''), 0, 20),
                    'date' => $d->date->toDateString(),
                    'read_by' => $readBy,
                    'read_by_names' => $readByNames,
                    'department' => $d->user->department ? $d->user->department->name : '\u672a\u6240\u5c5e',
                ];
            })->values();

            $departments = [
                [
                    'department' => '\u5168\u4f53',
                    'diaries' => $diariesArr,
                ],
            ];

            $meta = $paginator->toArray()['meta'] ?? null;
            $filters = [
                'q' => $q,
                'days' => $days,
                'perPage' => intval($request->input('perPage', 20)),
                'unread' => $unread,
            ];

            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => $departments,
                'date' => null,
                'meta' => $meta,
                'filters' => $filters,
                'routePrefix' => 'admin',
                'pageTitle' => '管理者 日報一覧',
                'headerTitle' => '管理者用 日報一覧',
            ]);
        }

        // if a specific date is requested, return only that date (full content page)
        if ($onlyDate) {
            $diaries = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->where('date', $onlyDate)
                ->orderBy('date', 'desc')
                ->get();

            if ($unread) {
                $diaries = $diaries->filter(function ($d) use ($currentUserId) {
                    return empty($d->read_by) || !is_array($d->read_by) || !in_array($currentUserId, $d->read_by);
                })->values();
            }

            $allReadIds = [];
            foreach ($diaries as $d) {
                if (!empty($d->read_by) && is_array($d->read_by)) {
                    $allReadIds = array_merge($allReadIds, $d->read_by);
                }
            }
            $allReadIds = array_values(array_unique($allReadIds));
            $namesMap = [];
            if (!empty($allReadIds)) {
                $namesMap = User::whereIn('id', $allReadIds)->pluck('name', 'id')->toArray();
            }

            $departments = [
                [
                    'department' => $onlyDate,
                    'diaries' => $diaries->map(function ($d) use ($namesMap) {
                        $readBy = $d->read_by ?? [];
                        $readByNames = array_map(function ($id) use ($namesMap) {
                            return $namesMap[$id] ?? ('ID:' . $id);
                        }, is_array($readBy) ? $readBy : []);

                        return [
                            'id' => $d->id,
                            'user_id' => $d->user_id,
                            'name' => $d->user->name ?? '',
                            'content' => $d->content ?? '',
                            'description' => mb_substr(strip_tags($d->content ?? ''), 0, 20),
                            'date' => $d->date->toDateString(),
                            'read_by' => $readBy,
                            'read_by_names' => $readByNames,
                            'department' => $d->user && $d->user->department ? $d->user->department->name : '\u672a\u6240\u5c5e',
                        ];
                    })->values(),
                ],
            ];

            $meta = null;

            $filters = ['date' => $onlyDate, 'fullContent' => true, 'unread' => $unread];

            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => $departments,
                'date' => $onlyDate,
                'meta' => $meta,
                'filters' => $filters,
                'routePrefix' => 'admin',
                'pageTitle' => '管理者 日報一覧',
                'headerTitle' => '管理者用 日報一覧',
            ]);
        }

        // get distinct dates within the window
        $datesQuery = Diary::whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId)
            ->where('date', '>=', now()->subDays($days));

        if ($unread) {
            $datesQuery->whereRaw("JSON_SEARCH(read_by, 'one', ?) IS NULL", [$currentUserId]);
        }

        $dates = $datesQuery->orderBy('date', 'desc')
            ->distinct()
            ->pluck('date')
            ->toArray();

        $totalDates = count($dates);
        $sliced = array_slice($dates, ($page - 1) * $perPage, $perPage);

        $diariesQuery = Diary::with('user.department')
            ->whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId)
            ->whereIn('date', $sliced)
            ->orderBy('date', 'desc');

        if ($unread) {
            $diariesQuery->whereRaw("JSON_SEARCH(read_by, 'one', ?) IS NULL", [$currentUserId]);
        }

        $diaries = $diariesQuery->get();

        // resolve read_by for these diaries
        $allReadIds = [];
        foreach ($diaries as $d) {
            if (!empty($d->read_by) && is_array($d->read_by)) {
                $allReadIds = array_merge($allReadIds, $d->read_by);
            }
        }
        $allReadIds = array_values(array_unique($allReadIds));
        $namesMap = [];
        if (!empty($allReadIds)) {
            $namesMap = User::whereIn('id', $allReadIds)->pluck('name', 'id')->toArray();
        }

        // group by date
        $grouped = $diaries->groupBy(function ($d) {
            return $d->date->toDateString();
        });

        $departments = [];
        foreach ($grouped as $dateKey => $list) {
            $departments[] = [
                'department' => $dateKey,
                'diaries' => $list->map(function ($d) use ($namesMap) {
                    $readBy = $d->read_by ?? [];
                    $readByNames = array_map(function ($id) use ($namesMap) {
                        return $namesMap[$id] ?? ('ID:' . $id);
                    }, is_array($readBy) ? $readBy : []);

                    return [
                        'id' => $d->id,
                        'user_id' => $d->user_id,
                        'name' => $d->user->name ?? '',
                        'description' => mb_substr(strip_tags($d->content ?? ''), 0, 20),
                        'date' => $d->date->toDateString(),
                        'read_by' => $readBy,
                        'read_by_names' => $readByNames,
                        'department' => $d->user && $d->user->department ? $d->user->department->name : '\u672a\u6240\u5c5e',
                    ];
                })->values(),
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

        return Inertia::render('Diaries/Interactions/Index', [
            'departments' => $departments,
            'date' => null,
            'meta' => $meta,
            'filters' => $filters,
            'routePrefix' => 'admin',
            'pageTitle' => '管理者 日報一覧',
            'headerTitle' => '管理者用 日報一覧',
        ]);
    }

    /**
     * Show a diary (read-only)
     */
    public function show(Diary $diary)
    {
        $this->authorize('view', $diary);
        $diary->load('user', 'comments');

        $readBy = $diary->read_by ?? [];
        $readByNames = [];
        if (!empty($readBy) && is_array($readBy)) {
            $names = User::whereIn('id', $readBy)->pluck('name', 'id')->toArray();
            $readByNames = array_map(function ($id) use ($names) {
                return $names[$id] ?? ('ID:' . $id);
            }, $readBy);
        }

        $diaryArray = $diary->toArray();
        $diaryArray['read_by_names'] = $readByNames;
        $diaryArray['comments'] = array_map(function ($c) {
            return [
                'id' => $c['id'] ?? null,
                'user_id' => $c['user_id'] ?? null,
                'user_name' => $c['user_name'] ?? null,
                'comment' => $c['comment'] ?? '',
                'created_at' => $c['created_at'] ?? null,
            ];
        }, $diaryArray['comments'] ?? []);

        return Inertia::render('Admin/Diaries/Show', [
            'diary' => $diaryArray,
        ]);
    }

    /**
     * Mark as read and optionally add admin comment.
     */
    public function markRead(Request $request, Diary $diary)
    {
        $this->authorize('view', $diary);
        $admin = Auth::user();

        $readBy = $diary->read_by ?? [];
        if (!in_array($admin->id, $readBy)) {
            $readBy[] = $admin->id;
        }

        if ($request->filled('comment')) {
            $diary->addComment($admin->id, $admin->name, $request->input('comment'));
        }

        $diary->read_by = array_values($readBy);
        $diary->save();

        return redirect()->back()->with('success', '既読/コメントを保存しました');
    }

    /**
     * Mark all diaries on a given date as read by current admin.
     */
    public function markReadAll(Request $request)
    {
        $this->authorize('viewAny', Diary::class);
        $admin = Auth::user();
        $date = $request->input('date');
        if (!$date) return redirect()->back()->with('error', '日付が指定されていません');

        $diaries = Diary::where('date', $date)->get();
        foreach ($diaries as $d) {
            // use model helper to normalize and persist safely
            if (!$d->hasBeenReadBy($admin->id)) {
                $d->addReadBy($admin->id);
            }
        }

        return redirect()->back()->with('success', '日付の全ての日報を既読にしました');
    }
}
