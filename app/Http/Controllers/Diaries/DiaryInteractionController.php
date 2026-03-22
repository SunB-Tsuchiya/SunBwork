<?php

namespace App\Http\Controllers\Diaries;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\User;
use App\Models\Team;
use App\Models\Unit;
use App\Models\WorkRecord;
use Illuminate\Support\Facades\Auth;

class DiaryInteractionController extends Controller
{
    /**
     * Build the list of permitted user IDs visible to the current actor
     * (admin -> company users, leader -> department/unit members).
     */
    protected function buildPermittedUserIds($currentUser)
    {
        // If user is superadmin, they can see all users' diaries
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

    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $isAdmin = ($currentUser->user_role ?? '') === 'admin';

        $userIds = $this->buildPermittedUserIds($currentUser);

        if (empty($userIds)) {
            // no permitted users -> render empty index
            $departments = [];
            $meta = null;
            $filters = ['q' => '', 'days' => 30, 'perPage' => 20];
            $routePrefix = $isAdmin ? 'admin' : 'leader';

            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => $departments,
                'date' => null,
                'meta' => $meta,
                'filters' => $filters,
                'routePrefix' => $routePrefix,
                'pageTitle' => ($isAdmin ? '管理者 日報一覧' : 'リーダー 日報一覧'),
                'headerTitle' => ($isAdmin ? '管理者用 日報一覧' : 'リーダー用 日報一覧'),
            ]);
        }

        // server-side filters and pagination
        $days = intval($request->input('days', 30));
        $perPage = intval($request->input('perPage', 30));
        $currentUserId = Auth::id();
        $q = trim((string) $request->input('q', ''));
        $page = max(1, intval($request->input('page', 1)));
        $unread = intval($request->input('unread', 0));
        $hasUnreadParam = $request->has('unread');
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

            if ($hasUnreadParam && $unread === 1) {
                // unread=1 -> only diaries NOT read by current user
                // treat NULL read_by as empty array
                $query->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
            } elseif ($hasUnreadParam && $unread === 0) {
                // unread=0 -> only diaries read by current user
                $query->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
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
                    'description' => strip_tags($d->content ?? ''),
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
                'unread' => $hasUnreadParam ? $unread : null,
            ];

            $routePrefix = $isAdmin ? 'admin' : 'leader';

            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => $departments,
                'date' => null,
                'meta' => $meta,
                'filters' => $filters,
                'routePrefix' => $routePrefix,
                'pageTitle' => ($isAdmin ? '管理者 日報一覧' : 'リーダー 日報一覧'),
                'headerTitle' => ($isAdmin ? '管理者用 日報一覧' : 'リーダー用 日報一覧'),
            ]);
        }

        // if a specific date is requested, return only that date (full content page)
        if ($onlyDate) {
            $filters = ['date' => $onlyDate, 'fullContent' => true, 'unread' => $hasUnreadParam ? $unread : null];

            $diariesQuery = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->where('date', $onlyDate)
                ->orderBy('date', 'desc');

            if ($hasUnreadParam && $unread === 1) {
                $diariesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
            } elseif ($hasUnreadParam && $unread === 0) {
                $diariesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
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
                            'description' => strip_tags($d->content ?? ''),
                            'date' => $d->date->toDateString(),
                            'read_by' => $readBy,
                            'read_by_names' => $readByNames,
                            'department' => $d->user && $d->user->department ? $d->user->department->name : '未所属',
                        ];
                    })->values(),
                ],
            ];

            $meta = null;
            $routePrefix = $isAdmin ? 'admin' : 'leader';

            return Inertia::render('Diaries/Interactions/ByDate', [
                'departments' => $departments,
                'date' => $onlyDate,
                'meta' => $meta,
                'filters' => $filters,
                'routePrefix' => $routePrefix,
                'pageTitle' => ($isAdmin ? '管理者 日報一覧' : 'リーダー 日報一覧'),
                'headerTitle' => ($isAdmin ? '管理者用 日報一覧' : 'リーダー用 日報一覧'),
            ]);
        }

        // get distinct dates within the window
        $datesQuery = Diary::whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId)
            ->where('date', '>=', now()->subDays($days));

        if ($hasUnreadParam && $unread === 1) {
            $datesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
        } elseif ($hasUnreadParam && $unread === 0) {
            $datesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
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

        if ($hasUnreadParam && $unread === 1) {
            $diariesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
        } elseif ($hasUnreadParam && $unread === 0) {
            $diariesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
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
                        'description' => strip_tags($d->content ?? ''),
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

        $filters = ['q' => '', 'days' => $days, 'perPage' => $perPage, 'unread' => $hasUnreadParam ? $unread : null];
        $routePrefix = $isAdmin ? 'admin' : 'leader';

        return Inertia::render('Diaries/Interactions/Index', [
            'departments' => $departments,
            'date' => null,
            'meta' => $meta,
            'filters' => $filters,
            'routePrefix' => $routePrefix,
            'pageTitle' => ($isAdmin ? '管理者 日報一覧' : 'リーダー 日報一覧'),
            'headerTitle' => ($isAdmin ? '管理者用 日報一覧' : 'リーダー用 日報一覧'),
        ]);
    }

    public function show(Diary $diary)
    {
        $currentUser = Auth::user();
        $isAdmin = ($currentUser->user_role ?? '') === 'admin';
        $permitted = $this->buildPermittedUserIds($currentUser);

        if (!in_array($diary->user_id, $permitted) && !$isAdmin) {
            abort(403, 'この日報を表示する権限がありません');
        }

        $diary->load('user.department', 'comments');

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
        $diaryArray['user_name'] = $diary->user?->name ?? '';
        $diaryArray['department_name'] = $diary->user?->department?->name ?? '';
        $diaryArray['comments'] = array_map(function ($c) {
            return [
                'id' => $c['id'] ?? null,
                'user_id' => $c['user_id'] ?? null,
                'user_name' => $c['user_name'] ?? null,
                'comment' => $c['comment'] ?? '',
                'created_at' => $c['created_at'] ?? null,
            ];
        }, $diaryArray['comments'] ?? []);

        // 勤務記録を取得
        $diaryDate = $diary->date instanceof \Carbon\Carbon
            ? $diary->date->toDateString()
            : date('Y-m-d', strtotime((string) $diary->date));
        $workRecord = WorkRecord::with('worktype')
            ->where('user_id', $diary->user_id)
            ->where('date', $diaryDate)
            ->first();
        $workRecordData = null;
        if ($workRecord) {
            $workRecordData = [
                'work_style'       => $workRecord->worktype?->name ?? null,
                'start_time'       => $workRecord->start_time ? substr($workRecord->start_time, 0, 5) : null,
                'end_time'         => $workRecord->end_time   ? substr($workRecord->end_time,   0, 5) : null,
                'overtime_minutes' => $workRecord->overtime_minutes ?? 0,
            ];
        }

        $routePrefix = $isAdmin ? 'admin' : 'leader';

        return Inertia::render('Diaries/Interactions/Show', [
            'diary'       => $diaryArray,
            'routePrefix' => $routePrefix,
            'workRecord'  => $workRecordData,
        ]);
    }

    public function markRead(Request $request, Diary $diary)
    {
        $currentUser = Auth::user();
        $isAdmin = ($currentUser->user_role ?? '') === 'admin';
        $permitted = $this->buildPermittedUserIds($currentUser);

        if (!in_array($diary->user_id, $permitted) && !$isAdmin) {
            abort(403, 'この日報を操作する権限がありません');
        }

        $readBy = $diary->read_by ?? [];
        if (!in_array($currentUser->id, $readBy)) {
            $readBy[] = $currentUser->id;
        }

        if ($request->filled('comment')) {
            $diary->addComment($currentUser->id, $currentUser->name, $request->input('comment'));
        }

        $diary->read_by = array_values($readBy);
        $diary->save();

        return redirect()->back()->with('success', '既読/コメントを保存しました');
    }

    public function markReadAll(Request $request)
    {
        $currentUser = Auth::user();
        $date = $request->input('date');
        if (!$date) return redirect()->back()->with('error', '日付が指定されていません');

        $userIds = $this->buildPermittedUserIds($currentUser);
        if (empty($userIds)) return redirect()->back()->with('error', '権限がありません');

        $diaries = Diary::whereIn('user_id', $userIds)->where('date', $date)->get();
        foreach ($diaries as $d) {
            if (!$d->hasBeenReadBy($currentUser->id)) {
                $d->addReadBy($currentUser->id);
            }
        }

        return redirect()->back()->with('success', '日付の全ての日報を既読にしました');
    }
}
