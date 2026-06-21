<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RoomReservation;
use App\Models\ScheduleAttendee;
use App\Models\SchedulePermissionSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleEventController extends Controller
{
    public function range(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date|after_or_equal:start',
        ]);

        $user  = Auth::user();
        $start = Carbon::createFromFormat('Y-m-d', $request->start, 'Asia/Tokyo')->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', $request->end,   'Asia/Tokyo')->endOfDay();

        // 自分のイベント（辞退した参加者は除外して返す）
        $ownEvents = Event::where('user_id', $user->id)
            ->where('is_company_event', true)
            ->whereBetween('starts_at', [$start, $end])
            ->with([
                'eventItemType:id,name,slug',
                'organizer:id,name',
                'attendees' => fn ($q) => $q->where('status', '!=', 'declined')->with('user:id,name'),
            ])
            ->get();

        // オーバーレイで追加されたユーザーのIDを収集（個人・会社・部署）
        $allOverlayUserIds = collect();

        // 個人オーバーレイ
        $directUserIds = DB::table('schedule_calendar_overlays')
            ->where('user_id', $user->id)
            ->whereNotNull('target_user_id')
            ->pluck('target_user_id');
        $allOverlayUserIds = $allOverlayUserIds->concat($directUserIds);

        // 会社オーバーレイ
        $companyIds = DB::table('schedule_calendar_overlays')
            ->where('user_id', $user->id)
            ->whereNotNull('target_company_id')
            ->pluck('target_company_id');
        if ($companyIds->isNotEmpty()) {
            $usersInCompanies = DB::table('users')
                ->whereIn('company_id', $companyIds)
                ->where('id', '!=', $user->id)
                ->pluck('id');
            $allOverlayUserIds = $allOverlayUserIds->concat($usersInCompanies);
        }

        // 部署オーバーレイ
        $deptIds = DB::table('schedule_calendar_overlays')
            ->where('user_id', $user->id)
            ->whereNotNull('target_department_id')
            ->pluck('target_department_id');
        if ($deptIds->isNotEmpty()) {
            $usersInDepts = DB::table('users')
                ->whereIn('department_id', $deptIds)
                ->where('id', '!=', $user->id)
                ->pluck('id');
            $allOverlayUserIds = $allOverlayUserIds->concat($usersInDepts);
        }

        $allOverlayUserIds = $allOverlayUserIds->unique()->values();

        $overlayEvents = collect();
        if ($allOverlayUserIds->isNotEmpty()) {
            $viewerCompanyId   = (int) $user->company_id;
            $groupCompanyIds   = $this->getGroupCompanyIds($viewerCompanyId);

            // company/group 公開範囲チェック用サブクエリ
            $sameCompanyUserIds = DB::table('users')
                ->where('company_id', $viewerCompanyId)
                ->pluck('id');
            $groupCompanyUserIds = DB::table('users')
                ->whereIn('company_id', $groupCompanyIds)
                ->pluck('id');

            $overlayEvents = Event::whereIn('user_id', $allOverlayUserIds)
                ->where('is_company_event', true)
                ->where(function ($q) use ($sameCompanyUserIds, $groupCompanyUserIds) {
                    $q->where('visibility', 'public')
                      ->orWhere(function ($q2) use ($sameCompanyUserIds) {
                          // company: 作成者が閲覧者と同社であること
                          $q2->where('visibility', 'company')
                             ->whereIn('user_id', $sameCompanyUserIds);
                      })
                      ->orWhere(function ($q2) use ($groupCompanyUserIds) {
                          // group: 作成者が同グループの会社であること
                          $q2->where('visibility', 'group')
                             ->whereIn('user_id', $groupCompanyUserIds);
                      });
                })
                ->whereBetween('starts_at', [$start, $end])
                ->with(['eventItemType:id,name,slug'])
                ->get()
                ->map(fn ($e) => array_merge($e->toArray(), ['is_own' => false]));
        }

        // 参加者として招待されたイベント（自分が作成していないもの・辞退済みは除外）
        $myAttendeeRows = DB::table('schedule_attendees')
            ->where('user_id', $user->id)
            ->where('status', '!=', 'declined')
            ->get(['event_id', 'status']);

        $attendeeEventIds   = $myAttendeeRows->pluck('event_id');
        $myAttendeeStatusMap = $myAttendeeRows->keyBy('event_id')->map(fn ($r) => $r->status);

        $attendeeEvents = collect();
        if ($attendeeEventIds->isNotEmpty()) {
            $attendeeEvents = Event::whereIn('id', $attendeeEventIds)
                ->where('user_id', '!=', $user->id)
                ->where('is_company_event', true)
                ->whereBetween('starts_at', [$start, $end])
                ->with(['eventItemType:id,name,slug', 'attendees.user:id,name'])
                ->get()
                ->map(fn ($e) => array_merge($e->toArray(), [
                    'is_own'              => false,
                    'as_attendee'         => true,
                    'my_attendee_status'  => $myAttendeeStatusMap->get($e->id, 'pending'),
                ]));
        }

        $events = $ownEvents->map(fn ($e) => array_merge($e->toArray(), ['is_own' => true]))
            ->concat($attendeeEvents)
            ->concat($overlayEvents);

        // 会議室予約（自分の会社の会議室のみ）
        $reservations = RoomReservation::whereHas(
            'meetingRoom',
            fn ($q) => $q->where('company_id', $user->company_id)
        )
            ->whereBetween('starts_at', [$start, $end])
            ->with(['meetingRoom:id,name,color', 'user:id,name', 'event.attendees.user:id,name', 'event.eventItemType:id,name,slug'])
            ->get()
            ->map(fn ($r) => array_merge($r->toArray(), ['_type' => 'reservation']));

        return response()->json([
            'events'       => $events,
            'reservations' => $reservations,
        ]);
    }

    public function conflicts(Request $request)
    {
        $request->validate([
            'starts_at'        => 'required|date',
            'ends_at'          => 'required|date|after:starts_at',
            'user_ids'         => 'required|array',
            'user_ids.*'       => 'integer|exists:users,id',
            'exclude_event_id' => 'nullable|integer|exists:events,id',
        ]);

        $user      = Auth::user();
        $start     = $request->starts_at;
        $end       = $request->ends_at;
        $userIds   = array_map('intval', $request->user_ids ?? []);
        $excludeId = $request->exclude_event_id ? (int) $request->exclude_event_id : null;

        $this->validateAttendeeScope($userIds, $user);

        $result = [];
        foreach ($userIds as $uid) {
            // 作成者として + 参加者（accepted/pending）として重複している予定をチェック
            $attendeeEventIds = DB::table('schedule_attendees')
                ->where('user_id', $uid)
                ->whereIn('status', ['accepted', 'pending'])
                ->pluck('event_id');

            $query = Event::where('is_company_event', true)
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->where(function ($q) use ($uid, $attendeeEventIds) {
                    $q->where('user_id', $uid)
                      ->orWhereIn('id', $attendeeEventIds);
                });

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $conflicting = $query->select('id', 'title', 'starts_at', 'ends_at')->distinct()->get();

            if ($conflicting->isNotEmpty()) {
                $userName = DB::table('users')->where('id', $uid)->value('name');
                $result[] = [
                    'user_id'   => $uid,
                    'user_name' => $userName,
                    'events'    => $conflicting->map(fn ($e) => [
                        'title'     => $e->title,
                        'starts_at' => $e->starts_at,
                        'ends_at'   => $e->ends_at,
                    ])->values(),
                ];
            }
        }

        return response()->json($result);
    }

    public function show(Event $event)
    {
        $user = Auth::user();
        $this->authorizeView($event, $user);

        $event->load(['eventItemType:id,name', 'organizer:id,name', 'attendees.user:id,name']);

        return response()->json($event);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                => 'required|string|max:255',
            'starts_at'            => 'required|date',
            'ends_at'              => 'required|date|after:starts_at',
            'event_item_type_id'   => 'nullable|exists:event_item_types,id',
            'meeting_definition_id' => 'nullable|exists:meeting_definitions,id',
            'destination'          => 'nullable|string|max:255',
            'body'                 => 'nullable|string',
            'is_company_event'     => 'boolean',
            'visibility'           => 'nullable|in:private,company,group,public',
            'attendee_ids'         => 'nullable|array',
            'attendee_ids.*'       => 'exists:users,id',
        ]);

        $this->checkSameDay($validated['starts_at'], $validated['ends_at']);

        $user = Auth::user();

        $event = Event::create([
            'user_id'               => $user->id,
            'title'                 => $validated['title'],
            'starts_at'             => $validated['starts_at'],
            'ends_at'               => $validated['ends_at'],
            'event_item_type_id'    => $validated['event_item_type_id'] ?? null,
            'meeting_definition_id' => $validated['meeting_definition_id'] ?? null,
            'destination'           => $validated['destination'] ?? null,
            'body'                  => $validated['body'] ?? null,
            'is_company_event'      => $validated['is_company_event'] ?? true,
            'visibility'            => $validated['visibility'] ?? 'company',
        ]);

        // 作成者を参加者として追加
        ScheduleAttendee::create([
            'event_id' => $event->id,
            'user_id'  => $user->id,
            'status'   => 'accepted',
            'added_by' => $user->id,
        ]);

        // 自分のイベント作成時は無条件で参加者を追加できる（テナントスコープのみチェック）
        $attendeeIds = array_map('intval', $validated['attendee_ids'] ?? []);
        $this->validateAttendeeScope($attendeeIds, $user);

        // 招待参加者を追加
        foreach ($attendeeIds as $uid) {
            if ($uid == $user->id) continue;
            ScheduleAttendee::firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $uid],
                ['status' => 'pending', 'added_by' => $user->id]
            );
        }

        $event->load(['eventItemType:id,name', 'attendees.user:id,name']);

        return response()->json(array_merge($event->toArray(), ['is_own' => true]), 201);
    }

    public function update(Request $request, Event $event)
    {
        $user = Auth::user();
        $this->authorizeEdit($event, $user);

        // M-2: 会議室予約に紐づくイベントは予約モーダルから編集する
        if ($event->room_reservation_id) {
            abort(422, 'この予定は会議室予約に紐づいています。会議室予約から編集してください');
        }

        $validated = $request->validate([
            'title'                 => 'sometimes|string|max:255',
            'starts_at'             => 'sometimes|date',
            'ends_at'               => 'sometimes|date|after:starts_at',
            'event_item_type_id'    => 'nullable|exists:event_item_types,id',
            'meeting_definition_id' => 'nullable|exists:meeting_definitions,id',
            'destination'           => 'nullable|string|max:255',
            'body'                  => 'nullable|string',
            'visibility'            => 'nullable|in:private,company,group,public',
        ]);

        $starts = $validated['starts_at'] ?? $event->starts_at;
        $ends   = $validated['ends_at']   ?? $event->ends_at;

        // 片方のみ更新される場合のガード（バリデーション after:starts_at は両方ある場合しか機能しない）
        if (Carbon::parse($starts)->gte(Carbon::parse($ends))) {
            abort(422, '終了時刻は開始時刻より後に設定してください');
        }

        $this->checkSameDay($starts, $ends);

        $event->update($validated);
        $event->load(['eventItemType:id,name', 'attendees.user:id,name']);

        return response()->json(array_merge($event->toArray(), ['is_own' => true]));
    }

    public function destroy(Event $event)
    {
        $user = Auth::user();
        $this->authorizeEdit($event, $user);

        // M-2: 会議室予約に紐づくイベントは予約モーダルから削除する
        if ($event->room_reservation_id) {
            abort(422, 'この予定は会議室予約に紐づいています。会議室予約から削除してください');
        }

        $event->delete();

        return response()->json(['ok' => true]);
    }

    private function checkSameDay($starts, $ends): void
    {
        if (Carbon::parse($starts)->toDateString() !== Carbon::parse($ends)->toDateString()) {
            abort(422, 'このページの予定は日をまたいで設定できません');
        }
    }

    /** 閲覧者の会社と同グループの全 company_id を返す */
    private function getGroupCompanyIds(int $companyId): array
    {
        $groupIds = DB::table('company_group_members')
            ->where('company_id', $companyId)
            ->pluck('company_group_id');

        if ($groupIds->isEmpty()) {
            return [$companyId];
        }

        return DB::table('company_group_members')
            ->whereIn('company_group_id', $groupIds)
            ->pluck('company_id')
            ->push($companyId)
            ->unique()
            ->values()
            ->all();
    }

    private function authorizeView(Event $event, $user): void
    {
        if ($event->user_id === $user->id) return;
        if (!$event->is_company_event) abort(403);
        if ($event->visibility === 'public') return;

        $creatorCompanyId = (int) DB::table('users')->where('id', $event->user_id)->value('company_id');

        if ($event->visibility === 'company') {
            if ($creatorCompanyId === (int) $user->company_id) return;
            abort(403);
        }

        if ($event->visibility === 'group') {
            if ($this->inSameGroup($creatorCompanyId, (int) $user->company_id)) return;
            abort(403);
        }

        abort(403);
    }

    // H-2: attendee_ids のテナントスコープチェック（同社またはグループ会社のみ許可）
    private function validateAttendeeScope(array $attendeeIds, $user): void
    {
        if (empty($attendeeIds)) return;
        $userCompanyId = (int) $user->company_id;
        DB::table('users')
            ->whereIn('id', $attendeeIds)
            ->pluck('company_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->each(function (int $companyId) use ($userCompanyId) {
                if (!$this->inSameGroup($userCompanyId, $companyId)) {
                    abort(422, '権限のない会社のユーザーを参加者に追加することはできません');
                }
            });
    }

    private function inSameGroup(int $companyIdA, int $companyIdB): bool
    {
        if ($companyIdA === $companyIdB) return true;
        return DB::table('company_group_members as a')
            ->join('company_group_members as b', 'a.company_group_id', '=', 'b.company_group_id')
            ->where('a.company_id', $companyIdA)
            ->where('b.company_id', $companyIdB)
            ->exists();
    }

    private function authorizeEdit(Event $event, $user): void
    {
        if ($event->user_id === $user->id) return;
        if ($event->organizer_id === $user->id) return;
        abort(403);
    }

}
