<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\User;
use App\Models\Team;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;

class DiaryController extends Controller
{
    /**
     * Show diaries for leader: only users who belong to departments or unit teams
     * where the current user is the configured leader.
     */
    public function index(Request $request)
    {
        $leader = Auth::user();
        $teams = Team::where('leader_id', $leader->id)
            ->whereIn('team_type', ['department', 'unit'])
            ->get();

        $userIds = [];
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

        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            $departments = [];
            $meta = null;
            $filters = ['q' => '', 'days' => 30, 'perPage' => 20];
            return Inertia::render('Leader/Diaries/Index', [
                'departments' => $departments,
                'date' => null,
                'meta' => $meta,
                'filters' => $filters,
            ]);
        }

        // server-side filters and pagination
        $days = intval($request->input('days', 30));
        $perPage = intval($request->input('perPage', 30)); // number of dates per page
        $currentUserId = Auth::id();
        $q = trim((string) $request->input('q', ''));
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
                    'department' => $d->user->department ? $d->user->department->name : '未所属',
                ];
            })->values();

            $departments = [
                [
                    'department' => '全体',
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

            return Inertia::render('Leader/Diaries/Index', [
                'departments' => $departments,
                'date' => null,
                'meta' => $meta,
                'filters' => $filters,
            ]);
        }

        // if a specific date is requested, return only that date (full content page)
        if ($onlyDate) {
            $sliced = [$onlyDate];
            // when viewing a single date we want full content
            $filters = ['date' => $onlyDate, 'fullContent' => true, 'unread' => $unread];

            $diariesQuery = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->where('date', $onlyDate)
                ->orderBy('date', 'desc');

            if ($unread) {
                $diariesQuery->whereRaw("JSON_SEARCH(read_by, 'one', ?) IS NULL", [$currentUserId]);
            }

            $diaries = $diariesQuery->get();

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
                            'department' => $d->user && $d->user->department ? $d->user->department->name : '未所属',
                        ];
                    })->values(),
                ],
            ];

            $meta = null;

            return Inertia::render('Leader/Diaries/Index', [
                'departments' => $departments,
                'date' => $onlyDate,
                'meta' => $meta,
                'filters' => $filters,
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
                        'department' => $d->user && $d->user->department ? $d->user->department->name : '未所属',
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

        return Inertia::render('Leader/Diaries/Index', [
            'departments' => $departments,
            'date' => null,
            'meta' => $meta,
            'filters' => $filters,
        ]);
    }

    public function show(Diary $diary)
    {
        $leader = Auth::user();

        // Build permitted user ids similarly to index
        $teams = Team::where('leader_id', $leader->id)->whereIn('team_type', ['department', 'unit'])->get();
        $permitted = [];
        foreach ($teams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                $permitted = array_merge($permitted, User::where('company_id', $team->company_id)->where('department_id', $team->department_id)->pluck('id')->toArray());
            }
            if ($team->team_type === 'unit') {
                $unit = Unit::where('company_id', $team->company_id)->where('department_id', $team->department_id)->where('name', $team->name)->first();
                if ($unit) $permitted = array_merge($permitted, $unit->members()->pluck('users.id')->toArray());
            }
        }
        $permitted = array_values(array_unique($permitted));

        if (!in_array($diary->user_id, $permitted) && ($leader->user_role ?? '') !== 'admin') {
            abort(403, 'この日報を表示する権限がありません');
        }

        $diary->load('user');

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

        return Inertia::render('Leader/Diaries/Show', [
            'diary' => $diaryArray,
        ]);
    }

    /**
     * Mark a single diary as read by the current leader and optionally add a comment.
     */
    public function markRead(Request $request, Diary $diary)
    {
        $leader = Auth::user();

        // permission: leader must be able to view this diary (reuse same permitted logic as show)
        $teams = Team::where('leader_id', $leader->id)->whereIn('team_type', ['department', 'unit'])->get();
        $permitted = [];
        foreach ($teams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                $permitted = array_merge($permitted, User::where('company_id', $team->company_id)->where('department_id', $team->department_id)->pluck('id')->toArray());
            }
            if ($team->team_type === 'unit') {
                $unit = Unit::where('company_id', $team->company_id)->where('department_id', $team->department_id)->where('name', $team->name)->first();
                if ($unit) $permitted = array_merge($permitted, $unit->members()->pluck('users.id')->toArray());
            }
        }
        $permitted = array_values(array_unique($permitted));

        if (!in_array($diary->user_id, $permitted) && ($leader->user_role ?? '') !== 'admin') {
            abort(403, 'この日報を表示する権限がありません');
        }

        $readBy = $diary->read_by ?? [];
        if (!in_array($leader->id, $readBy)) {
            $readBy[] = $leader->id;
        }

        if ($request->filled('comment')) {
            $diary->addComment($leader->id, $leader->name, $request->input('comment'));
        }

        $diary->read_by = array_values($readBy);
        $diary->save();

        return redirect()->back()->with('success', '既読/コメントを保存しました');
    }

    /**
     * Mark all diaries on a given date as read by current leader (if permitted).
     */
    public function markReadAll(Request $request)
    {
        $leader = Auth::user();
        $date = $request->input('date');
        if (!$date) return redirect()->back()->with('error', '日付が指定されていません');

        // find diaries for users this leader can view
        $teams = Team::where('leader_id', $leader->id)->whereIn('team_type', ['department', 'unit'])->get();
        $userIds = [];
        foreach ($teams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                $userIds = array_merge($userIds, User::where('company_id', $team->company_id)->where('department_id', $team->department_id)->pluck('id')->toArray());
            }
            if ($team->team_type === 'unit') {
                $unit = Unit::where('company_id', $team->company_id)->where('department_id', $team->department_id)->where('name', $team->name)->first();
                if ($unit) $userIds = array_merge($userIds, $unit->members()->pluck('users.id')->toArray());
            }
        }
        $userIds = array_values(array_unique($userIds));

        $diaries = Diary::whereIn('user_id', $userIds)->where('date', $date)->get();
        foreach ($diaries as $d) {
            // use model helper to normalize and persist safely
            if (!$d->hasBeenReadBy($leader->id)) {
                $d->addReadBy($leader->id);
            }
        }

        return redirect()->back()->with('success', '日付の全ての日報を既読にしました');
    }
}
