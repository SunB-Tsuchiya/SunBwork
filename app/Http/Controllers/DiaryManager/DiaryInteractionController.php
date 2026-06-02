<?php

namespace App\Http\Controllers\DiaryManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\User;
use App\Models\WorkRecord;
use Illuminate\Support\Facades\Auth;

class DiaryInteractionController extends Controller
{
    protected function buildPermittedUserIds(): array
    {
        return Auth::user()->diaryManagerMemberIds();
    }

    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $userIds = $this->buildPermittedUserIds();

        if (empty($userIds)) {
            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => [],
                'date'        => null,
                'meta'        => null,
                'filters'     => ['q' => '', 'year' => null, 'month' => null, 'period' => null, 'perPage' => 20],
                'routePrefix' => 'diary_manager',
                'pageTitle'   => '日報管理',
                'headerTitle' => '日報管理',
            ]);
        }

        $year    = $request->input('year', null);
        $month   = $request->input('month', null);
        $period  = $request->input('period', null);
        if ($period !== 'all' && ! $year && ! $month) {
            $year  = now()->year;
            $month = now()->month;
        }

        if ($period === 'all') {
            $lower = null;
            $upper = null;
        } else {
            $y     = intval($year);
            $m     = intval($month);
            $lower = sprintf('%04d-%02d-01', $y, $m);
            $upper = \Carbon\Carbon::createFromDate($y, $m, 1)->endOfMonth()->toDateString();
        }

        $perPage       = intval($request->input('perPage', 30));
        $currentUserId = Auth::id();
        $q             = trim((string) $request->input('q', ''));
        $page          = max(1, intval($request->input('page', 1)));
        $unread        = intval($request->input('unread', 0));
        $hasUnreadParam = $request->has('unread');
        $onlyDate      = $request->input('date', null);

        // full-text search path
        if ($q !== '') {
            $query = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->when($lower !== null, fn($qb) => $qb->where('date', '>=', $lower)->where('date', '<=', $upper))
                ->where(function ($qq) use ($q) {
                    if (is_numeric($q)) {
                        $qq->orWhere('id', intval($q));
                    }
                    $qq->orWhere('content', 'like', '%' . $q . '%')
                        ->orWhereHas('user', fn($qu) => $qu->where('name', 'like', '%' . $q . '%'));
                });

            $this->applyUnreadFilter($query, $hasUnreadParam, $unread, $currentUserId);

            $paginator  = $query->orderBy('date', 'desc')
                ->paginate(intval($request->input('perPage', 20)))
                ->withQueryString();
            $collection = $paginator->getCollection();
            $namesMap   = $this->buildNamesMap($collection);

            $departments = [[
                'department' => '全体',
                'diaries'    => $collection->map(fn($d) => $this->diaryRow($d, $namesMap))->values(),
            ]];

            return Inertia::render('Diaries/Interactions/Index', [
                'departments' => $departments,
                'date'        => null,
                'meta'        => $paginator->toArray()['meta'] ?? null,
                'filters'     => ['q' => $q, 'year' => $year, 'month' => $month, 'period' => $period, 'perPage' => intval($request->input('perPage', 20)), 'unread' => $hasUnreadParam ? $unread : null],
                'routePrefix' => 'diary_manager',
                'pageTitle'   => '日報管理',
                'headerTitle' => '日報管理',
            ]);
        }

        // single date path
        if ($onlyDate) {
            $diariesQuery = Diary::with('user.department')
                ->whereIn('user_id', $userIds)
                ->where('user_id', '!=', $currentUserId)
                ->where('date', $onlyDate)
                ->orderBy('date', 'desc');

            $this->applyUnreadFilter($diariesQuery, $hasUnreadParam, $unread, $currentUserId);
            $diaries  = $diariesQuery->get();
            $namesMap = $this->buildNamesMap($diaries);

            $departments = [[
                'department' => $onlyDate,
                'diaries'    => $diaries->map(function ($d) use ($namesMap) {
                    $row            = $this->diaryRow($d, $namesMap);
                    $row['content'] = $d->content ?? '';
                    return $row;
                })->values(),
            ]];

            return Inertia::render('Diaries/Interactions/ByDate', [
                'departments' => $departments,
                'date'        => $onlyDate,
                'meta'        => null,
                'filters'     => ['date' => $onlyDate, 'fullContent' => true, 'unread' => $hasUnreadParam ? $unread : null],
                'routePrefix' => 'diary_manager',
                'pageTitle'   => '日報管理',
                'headerTitle' => '日報管理',
            ]);
        }

        // date-list pagination path
        $datesQuery = Diary::whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId);
        if ($lower !== null) {
            $datesQuery->where('date', '>=', $lower)->where('date', '<=', $upper);
        }
        $this->applyUnreadFilter($datesQuery, $hasUnreadParam, $unread, $currentUserId);
        $dates      = $datesQuery->orderBy('date', 'desc')->distinct()->pluck('date')->toArray();
        $totalDates = count($dates);
        $sliced     = array_slice($dates, ($page - 1) * $perPage, $perPage);

        $diariesQuery = Diary::with('user.department')
            ->whereIn('user_id', $userIds)
            ->where('user_id', '!=', $currentUserId)
            ->whereIn('date', $sliced)
            ->orderBy('date', 'desc');
        $this->applyUnreadFilter($diariesQuery, $hasUnreadParam, $unread, $currentUserId);
        $diaries  = $diariesQuery->get();
        $namesMap = $this->buildNamesMap($diaries);

        $grouped     = $diaries->groupBy(fn($d) => $d->date->toDateString());
        $departments = [];
        foreach ($grouped as $dateKey => $list) {
            $departments[] = [
                'department' => $dateKey,
                'diaries'    => $list->map(fn($d) => $this->diaryRow($d, $namesMap))->values(),
            ];
        }

        $lastPage = (int) ceil($totalDates / max(1, $perPage));
        $meta     = ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $totalDates];
        $filters  = ['q' => '', 'year' => $year, 'month' => $month, 'period' => $period, 'perPage' => $perPage, 'unread' => $hasUnreadParam ? $unread : null];

        return Inertia::render('Diaries/Interactions/Index', [
            'departments' => $departments,
            'date'        => null,
            'meta'        => $meta,
            'filters'     => $filters,
            'routePrefix' => 'diary_manager',
            'pageTitle'   => '日報管理',
            'headerTitle' => '日報管理',
        ]);
    }

    public function show(Diary $diary)
    {
        $permitted = $this->buildPermittedUserIds();

        if (! in_array($diary->user_id, $permitted)) {
            abort(403, 'この日報を表示する権限がありません');
        }

        $diary->load('user.department', 'comments');

        $readBy           = $diary->read_by ?? [];
        $readByNames      = [];
        $readByStructured = [];

        if (! empty($readBy) && is_array($readBy)) {
            $names            = User::whereIn('id', $readBy)->pluck('name', 'id')->toArray();
            $readByNames      = array_map(fn($id) => $names[$id] ?? ('ID:' . $id), $readBy);
            $readByStructured = array_map(fn($id) => ['id' => $id, 'name' => $names[$id] ?? ('ID:' . $id)], $readBy);
        }

        $diaryArray                  = $diary->toArray();
        $diaryArray['read_by_names'] = $readByNames;
        $diaryArray['read_by']       = $readByStructured;
        $diaryArray['user_name']     = $diary->user?->name ?? '';
        $diaryArray['department_name'] = $diary->user?->department?->name ?? '';
        $diaryArray['comments']      = array_map(fn($c) => [
            'id'         => $c['id'] ?? null,
            'user_id'    => $c['user_id'] ?? null,
            'user_name'  => $c['user_name'] ?? null,
            'comment'    => $c['comment'] ?? '',
            'created_at' => $c['created_at'] ?? null,
        ], $diaryArray['comments'] ?? []);

        $diaryDate    = $diary->date instanceof \Carbon\Carbon
            ? $diary->date->toDateString()
            : date('Y-m-d', strtotime((string) $diary->date));
        $workRecord   = WorkRecord::with('worktype')
            ->where('user_id', $diary->user_id)
            ->where('date', $diaryDate)
            ->first();
        $workRecordData = $workRecord ? [
            'work_style'       => $workRecord->worktype?->name ?? null,
            'start_time'       => $workRecord->start_time ? substr($workRecord->start_time, 0, 5) : null,
            'end_time'         => $workRecord->end_time   ? substr($workRecord->end_time,   0, 5) : null,
            'overtime_minutes' => $workRecord->overtime_minutes ?? 0,
        ] : null;

        return Inertia::render('Diaries/Interactions/Show', [
            'diary'       => $diaryArray,
            'routePrefix' => 'diary_manager',
            'workRecord'  => $workRecordData,
        ]);
    }

    public function markRead(Request $request, Diary $diary)
    {
        $permitted = $this->buildPermittedUserIds();

        if (! in_array($diary->user_id, $permitted)) {
            abort(403, 'この日報を操作する権限がありません');
        }

        $currentUser = Auth::user();
        $readBy      = $diary->read_by ?? [];

        if (! in_array($currentUser->id, $readBy)) {
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
        $date = $request->input('date');
        if (! $date) {
            return redirect()->back()->with('error', '日付が指定されていません');
        }

        $currentUser = Auth::user();
        $userIds     = $this->buildPermittedUserIds();

        if (empty($userIds)) {
            return redirect()->back()->with('error', '権限がありません');
        }

        $diaries = Diary::whereIn('user_id', $userIds)->where('date', $date)->get();
        foreach ($diaries as $d) {
            if (! $d->hasBeenReadBy($currentUser->id)) {
                $d->addReadBy($currentUser->id);
            }
        }

        return redirect()->back()->with('success', '日付の全ての日報を既読にしました');
    }

    private function applyUnreadFilter($query, bool $hasParam, int $unread, int $currentUserId): void
    {
        if (! $hasParam) {
            return;
        }
        if ($unread === 1) {
            $query->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 0", [$currentUserId]);
        } else {
            $query->whereRaw("JSON_CONTAINS(COALESCE(read_by, JSON_ARRAY()), JSON_ARRAY(?)) = 1", [$currentUserId]);
        }
    }

    private function buildNamesMap($collection): array
    {
        $allReadIds = [];
        foreach ($collection as $d) {
            if (! empty($d->read_by) && is_array($d->read_by)) {
                $allReadIds = array_merge($allReadIds, $d->read_by);
            }
        }
        if (empty($allReadIds)) {
            return [];
        }
        return User::whereIn('id', array_values(array_unique($allReadIds)))->pluck('name', 'id')->toArray();
    }

    private function diaryRow($d, array $namesMap): array
    {
        $readBy      = $d->read_by ?? [];
        $readByNames = array_map(fn($id) => $namesMap[$id] ?? ('ID:' . $id), is_array($readBy) ? $readBy : []);
        return [
            'id'            => $d->id,
            'user_id'       => $d->user_id,
            'name'          => $d->user->name ?? '',
            'description'   => strip_tags($d->content ?? ''),
            'date'          => $d->date->toDateString(),
            'read_by'       => $readBy,
            'read_by_names' => $readByNames,
            'department'    => $d->user?->department?->name ?? '未所属',
        ];
    }
}
