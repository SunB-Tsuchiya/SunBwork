<?php

namespace App\Http\Controllers\Diaries;

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
     * Unified index for diaries entries (for listing) — this controller will delegate to queries
     * that previously lived in Admin/DiaryAdminController and Leader/DiaryController.
     * For now this is a shim that calls Diary::all() as a placeholder; we'll incrementally
     * replace logic with merged behaviour (permissions-filtering) in follow-ups.
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();

        // Determine if user is admin or leader and build permitted user ids
        $isAdmin = ($currentUser->user_role ?? '') === 'admin';
        $isLeader = false;
        $userIds = [];

        // quick leader check
        $isLeader = \App\Models\Team::where('leader_id', $currentUser->id)->whereIn('team_type', ['department', 'unit'])->exists();

        if ($isAdmin) {
            // admins see users in their company
            $companyId = $currentUser->company_id;
            $users = User::where('company_id', $companyId)->get();
            $userIds = $users->pluck('id')->toArray();
        } elseif ($isLeader) {
            // leader: gather users from teams (departments and unit members)
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

            $userIds = array_values(array_unique(array_filter($userIds)));
        } else {
            // not permitted to view interactions
            abort(403, '権限がありません');
        }

        // server-side filters and pagination (adopt Leader/Admin behaviour)
        $days = intval($request->input('days', 30));
        $perPage = intval($request->input('perPage', 30));
        $currentUserId = Auth::id();
        $q = trim((string) $request->input('q', ''));
        $page = max(1, intval($request->input('page', 1)));
        $unread = intval($request->input('unread', 0));
        $onlyDate = $request->input('date', null);

        // free-text query -> diary-level pagination
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

            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => $departments,
                'date' => null,
                'meta' => $meta,
                'filters' => $filters,
            ]);
        }

        // if a specific date is requested, return only that date (full content page)
        if ($onlyDate) {
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

            return Inertia::render('Diaries/Interactions/Index', [
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

        return Inertia::render('Diaries/Interactions/Index', [
            'departments' => $departments,
            'date' => null,
            'meta' => $meta,
            'filters' => $filters,
        ]);
    }

    /**
     * Mark all diaries on a given date as read by current user (placeholder delegating logic)
     */
    public function markReadAll(Request $request)
    {
        $user = Auth::user();
        $date = $request->input('date');
        if (!$date) return redirect()->back()->with('error', '日付が指定されていません');

        // placeholder: mark read for all diaries on that date that are visible to current user
        $diaries = Diary::where('date', $date)->get();
        foreach ($diaries as $d) {
            if (!$d->hasBeenReadBy($user->id)) {
                $d->addReadBy($user->id);
            }
        }

        return redirect()->back()->with('success', '日付の全ての日報を既読にしました');
    }
}
