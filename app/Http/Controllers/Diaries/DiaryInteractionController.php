<?php

namespace App\Http\Controllers\Diaries;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\User;
use App\Models\Team;
use App\Models\Unit;
use App\Models\WorkRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiaryInteractionController extends Controller
{
    use ChecksAdminPermission, ChecksLeaderPermission, ResolvesContextCompany;
    /**
     * Build the list of permitted user IDs visible to the current actor
     * (admin -> company users, leader -> department/unit members).
     */
    protected function buildPermittedUserIds($currentUser)
    {
        // SuperAdmin: コンテキスト会社に絞る（未選択=グローバルモードは全社）
        if (method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin()) {
            $contextCompanyId = $this->contextCompanyId();
            if ($contextCompanyId) {
                return User::where('company_id', $contextCompanyId)->pluck('id')->toArray();
            }
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

        // リーダーとして所属するチーム（leader_id）
        $leaderTeams = Team::where('leader_id', $currentUser->id)
            ->whereIn('team_type', ['department', 'unit'])
            ->where('can_read_diary', true)
            ->get();

        // サブリーダーとして所属するチーム（department ならその部署全員、unit ならユニットメンバーのみ）
        $subLeaderTeamIds = DB::table('team_sub_leaders')
            ->where('user_id', $currentUser->id)
            ->pluck('team_id');
        $subLeaderTeams = Team::whereIn('id', $subLeaderTeamIds)
            ->whereIn('team_type', ['department', 'unit'])
            ->where('can_read_diary', true)
            ->get();

        $allTeams = $leaderTeams->merge($subLeaderTeams)->unique('id');

        foreach ($allTeams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                // 部署リーダー: 部署内の全ユーザー
                $deptUsers = User::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->pluck('id')
                    ->toArray();
                $userIds = array_merge($userIds, $deptUsers);
            }

            if ($team->team_type === 'unit') {
                // ユニットリーダー/サブリーダー: ユニットメンバーのみ
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

        return array_values(array_unique(array_filter($userIds)));
    }

    private function buildNamesMap($diaries): array
    {
        $allReadIds = [];
        foreach ($diaries as $d) {
            if (!empty($d->read_by) && is_array($d->read_by)) {
                $allReadIds = array_merge($allReadIds, $d->read_by);
            }
        }

        $allReadIds = array_values(array_unique($allReadIds));
        if (empty($allReadIds)) {
            return [];
        }

        return User::whereIn('id', $allReadIds)->pluck('name', 'id')->toArray();
    }

    private function diaryRow(Diary $d, array $namesMap, bool $includeContent = false): array
    {
        $readBy = $d->read_by ?? [];
        $readByNames = array_map(function ($id) use ($namesMap) {
            return $namesMap[$id] ?? ('ID:' . $id);
        }, is_array($readBy) ? $readBy : []);

        $row = [
            'id' => $d->id,
            'row_key' => 'diary-' . $d->id,
            'user_id' => $d->user_id,
            'name' => $d->user->name ?? '',
            'description' => strip_tags($d->content ?? ''),
            'date' => $d->date->toDateString(),
            'read_by' => $readBy,
            'read_by_names' => $readByNames,
            'department' => $d->user && $d->user->department ? $d->user->department->name : '未所属',
            'has_diary' => true,
            'has_work_record' => false,
        ];

        if ($includeContent) {
            $row['content'] = $d->content ?? '';
        }

        return $row;
    }

    private function normalizeWorkRecordDate(WorkRecord $record): string
    {
        return $record->date instanceof \Carbon\CarbonInterface
            ? $record->date->toDateString()
            : date('Y-m-d', strtotime((string) $record->date));
    }

    private function workRecordDescription(WorkRecord $record): string
    {
        $start = $record->start_time ? substr($record->start_time, 0, 5) : null;
        $end = $record->end_time ? substr($record->end_time, 0, 5) : null;
        $parts = [];
        if ($record->worktype?->name) {
            $parts[] = $record->worktype->name;
        }
        if ($start || $end) {
            $parts[] = trim(($start ? '開始 ' . $start : '') . ' ' . ($end ? '終了 ' . $end : ''));
        }

        return implode(' / ', $parts);
    }

    private function workRecordRow($records): array
    {
        $records = collect($records)->values();
        $record = $records->first();
        $date = $this->normalizeWorkRecordDate($record);
        $details = $records
            ->map(fn ($item) => $this->workRecordDescription($item))
            ->filter()
            ->unique()
            ->values();
        $description = '日報本文なし（タイムテーブルあり）';
        if ($details->isNotEmpty()) {
            $description .= ' / ' . $details->implode('、');
        }

        return [
            'id' => null,
            'row_key' => 'work-record-' . $record->user_id . '-' . $date,
            'user_id' => $record->user_id,
            'name' => $record->user->name ?? '',
            'content' => $description,
            'description' => $description,
            'date' => $date,
            'read_by' => [],
            'read_by_names' => [],
            'department' => $record->user && $record->user->department ? $record->user->department->name : '未所属',
            'has_diary' => false,
            'has_work_record' => true,
        ];
    }

    private function workRecordOnlyRows(array $existingDiaryKeys, array $userIds, int $currentUserId, ?string $lower, ?string $upper, ?string $date = null)
    {
        $query = WorkRecord::with('user.department', 'worktype')
            ->whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId);

        if ($date !== null) {
            $query->where('date', $date);
        } elseif ($lower !== null) {
            $query->where('date', '>=', $lower)->where('date', '<=', $upper);
        }

        return $query->orderBy('date', 'desc')
            ->orderBy('user_id')
            ->get()
            ->filter(function ($record) use ($existingDiaryKeys) {
                $date = $this->normalizeWorkRecordDate($record);
                return !isset($existingDiaryKeys[$record->user_id . '|' . $date]);
            })
            ->groupBy(fn ($record) => $record->user_id . '|' . $this->normalizeWorkRecordDate($record))
            ->map(fn ($records) => $this->workRecordRow($records))
            ->values();
    }

    public function index(Request $request)
    {
        $this->requireAdminPermission('diary_management');
        $this->requireLeaderPermission('diary_management');
        $currentUser = Auth::user();
        $isAdmin = in_array($currentUser->user_role ?? '', ['admin', 'superadmin']);

        // SuperAdmin グローバルモード時は会社未選択警告
        if ($currentUser->isSuperAdmin() && $this->contextCompanyId() === null) {
            return Inertia::render('Diaries/Interactions/Index', [
                'noCompanySelected' => true,
                'departments'       => [],
                'date'              => null,
                'meta'              => null,
                'filters'           => ['q' => '', 'year' => null, 'month' => null, 'period' => null, 'perPage' => 20],
                'routePrefix'       => 'admin',
                'pageTitle'         => '管理者 日報一覧',
                'headerTitle'       => '管理者用 日報一覧',
            ]);
        }

        $userIds = $this->buildPermittedUserIds($currentUser);

        if (empty($userIds)) {
            // no permitted users -> render empty index
            $departments = [];
            $meta = null;
            $filters = ['q' => '', 'year' => null, 'month' => null, 'period' => null, 'perPage' => 20];
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

        // server-side filters
        $year   = $request->input('year', null);
        $month  = $request->input('month', null);
        $period = $request->input('period', null);
        // デフォルト: 何も指定がなければ現在月を表示
        if ($period !== 'all' && !$year && !$month) {
            $year  = now()->year;
            $month = now()->month;
        }
        // 日付範囲を構築
        if ($period === 'all') {
            $lower = null;
            $upper = null;
        } else {
            $y = intval($year);
            $m = intval($month);
            $lower = sprintf('%04d-%02d-01', $y, $m);
            $upper = \Carbon\Carbon::createFromDate($y, $m, 1)->endOfMonth()->toDateString();
        }
        $perPage = intval($request->input('perPage', 30));
        $currentUserId = Auth::id();
        $q = trim((string) $request->input('q', ''));
        $page = max(1, intval($request->input('page', 1)));
        $unread = intval($request->input('unread', 0));
        $hasUnreadParam = $request->has('unread');
        $onlyDate = $request->input('date', null);

        // free-text query -> full diary list
        if ($q !== '') {
            $query = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->when($lower !== null, fn ($qb) => $qb->where('date', '>=', $lower)->where('date', '<=', $upper))
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

            $collection = $query->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $namesMap = $this->buildNamesMap($collection);
            $diariesArr = $collection->map(fn ($d) => $this->diaryRow($d, $namesMap))->values();

            $departments = [
                [
                    'department' => '全体',
                    'diaries' => $diariesArr,
                ],
            ];

            $meta = null;
            $filters = [
                'q' => $q,
                'year' => $year,
                'month' => $month,
                'period' => $period,
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

            $namesMap = $this->buildNamesMap($diaries);
            $existingDiaryKeys = $diaries->mapWithKeys(function ($d) {
                return [$d->user_id . '|' . $d->date->toDateString() => true];
            })->all();
            $rows = $diaries->map(fn ($d) => $this->diaryRow($d, $namesMap, true))
                ->concat($this->workRecordOnlyRows($existingDiaryKeys, $userIds, $currentUserId, null, null, $onlyDate))
                ->sortBy([
                    ['department', 'asc'],
                    ['name', 'asc'],
                ])
                ->values();

            $departments = [
                [
                    'department' => $onlyDate,
                    'diaries' => $rows,
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
            ->where('user_id', '!=', $currentUserId);
        if ($lower !== null) {
            $datesQuery->where('date', '>=', $lower)->where('date', '<=', $upper);
        }

        if ($hasUnreadParam && $unread === 1) {
            $datesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
        } elseif ($hasUnreadParam && $unread === 0) {
            $datesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
        }

        $diaryDates = $datesQuery->orderBy('date', 'desc')
            ->distinct()
            ->pluck('date')
            ->toArray();

        $workRecordDates = collect();
        if (!($hasUnreadParam && $unread === 0)) {
            $workRecordDatesQuery = WorkRecord::whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId);
            if ($lower !== null) {
                $workRecordDatesQuery->where('date', '>=', $lower)->where('date', '<=', $upper);
            }
            $workRecordDates = $workRecordDatesQuery->orderBy('date', 'desc')
                ->distinct()
                ->pluck('date');
        }

        $dates = collect($diaryDates)
            ->merge($workRecordDates)
            ->map(function ($date) {
                return $date instanceof \Carbon\CarbonInterface
                    ? $date->toDateString()
                    : date('Y-m-d', strtotime((string) $date));
            })
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $diariesQuery = Diary::with('user.department')
            ->whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId)
            ->whereIn('date', $dates)
            ->orderBy('date', 'desc');

        if ($hasUnreadParam && $unread === 1) {
            $diariesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
        } elseif ($hasUnreadParam && $unread === 0) {
            $diariesQuery->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
        }

        $diaries = $diariesQuery->get();

        $namesMap = $this->buildNamesMap($diaries);
        $existingDiaryKeys = $diaries->mapWithKeys(function ($d) {
            return [$d->user_id . '|' . $d->date->toDateString() => true];
        })->all();
        $workRecordOnlyRows = ($hasUnreadParam && $unread === 0)
            ? collect()
            : $this->workRecordOnlyRows($existingDiaryKeys, $userIds, $currentUserId, $lower, $upper);

        // group by date
        $rows = $diaries->map(fn ($d) => $this->diaryRow($d, $namesMap))
            ->concat($workRecordOnlyRows)
            ->sortByDesc('date')
            ->values();

        $grouped = $rows->groupBy('date');

        $departments = [];
        foreach ($grouped as $dateKey => $list) {
            $departments[] = [
                'department' => $dateKey,
                'diaries' => $list->values(),
            ];
        }

        $meta = null;

        $filters = ['q' => '', 'year' => $year, 'month' => $month, 'period' => $period, 'perPage' => $perPage, 'unread' => $hasUnreadParam ? $unread : null];
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
        $this->requireAdminPermission('diary_management');
        $this->requireLeaderPermission('diary_management');
        $currentUser = Auth::user();
        $isAdmin = in_array($currentUser->user_role ?? '', ['admin', 'superadmin']);
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
        $this->requireAdminPermission('diary_management');
        $this->requireLeaderPermission('diary_management');
        $currentUser = Auth::user();
        $isAdmin = in_array($currentUser->user_role ?? '', ['admin', 'superadmin']);
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
        $this->requireAdminPermission('diary_management');
        $this->requireLeaderPermission('diary_management');
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
