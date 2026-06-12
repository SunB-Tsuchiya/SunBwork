<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RoomReservation;
use App\Models\ScheduleAttendee;
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

        // 自分のイベント
        $ownEvents = Event::where('user_id', $user->id)
            ->where('is_company_event', true)
            ->whereBetween('starts_at', [$start, $end])
            ->with(['eventItemType:id,name', 'organizer:id,name', 'attendees.user:id,name'])
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
            $overlayEvents = Event::whereIn('user_id', $allOverlayUserIds)
                ->where('is_company_event', true)
                ->whereIn('visibility', ['company', 'group', 'public'])
                ->whereBetween('starts_at', [$start, $end])
                ->with(['eventItemType:id,name'])
                ->get()
                ->map(fn ($e) => array_merge($e->toArray(), ['is_own' => false]));
        }

        $events = $ownEvents->map(fn ($e) => array_merge($e->toArray(), ['is_own' => true]))
            ->concat($overlayEvents);

        // 会議室予約（自分の会社の会議室のみ）
        $reservations = RoomReservation::whereHas(
            'meetingRoom',
            fn ($q) => $q->where('company_id', $user->company_id)
        )
            ->whereBetween('starts_at', [$start, $end])
            ->with('meetingRoom:id,name,color')
            ->get()
            ->map(fn ($r) => array_merge($r->toArray(), ['_type' => 'reservation']));

        return response()->json([
            'events'       => $events,
            'reservations' => $reservations,
        ]);
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
            'title'              => 'required|string|max:255',
            'starts_at'          => 'required|date',
            'ends_at'            => 'required|date|after:starts_at',
            'event_item_type_id' => 'nullable|exists:event_item_types,id',
            'body'               => 'nullable|string',
            'is_company_event'   => 'boolean',
            'visibility'         => 'nullable|in:private,company,group,public',
            'attendee_ids'       => 'nullable|array',
            'attendee_ids.*'     => 'exists:users,id',
        ]);

        $user = Auth::user();

        $event = Event::create([
            'user_id'            => $user->id,
            'title'              => $validated['title'],
            'starts_at'          => $validated['starts_at'],
            'ends_at'            => $validated['ends_at'],
            'event_item_type_id' => $validated['event_item_type_id'] ?? null,
            'body'               => $validated['body'] ?? null,
            'is_company_event'   => $validated['is_company_event'] ?? true,
            'visibility'         => $validated['visibility'] ?? 'company',
        ]);

        // 作成者を参加者として追加
        ScheduleAttendee::create([
            'event_id' => $event->id,
            'user_id'  => $user->id,
            'status'   => 'accepted',
            'added_by' => $user->id,
        ]);

        // 招待参加者を追加
        foreach ($validated['attendee_ids'] ?? [] as $uid) {
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

        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'starts_at'        => 'sometimes|date',
            'ends_at'          => 'sometimes|date|after:starts_at',
            'event_item_type_id' => 'nullable|exists:event_item_types,id',
            'body'             => 'nullable|string',
            'visibility'       => 'nullable|in:private,company,group,public',
        ]);

        $event->update($validated);
        $event->load(['eventItemType:id,name', 'attendees.user:id,name']);

        return response()->json(array_merge($event->toArray(), ['is_own' => true]));
    }

    public function destroy(Event $event)
    {
        $user = Auth::user();
        $this->authorizeEdit($event, $user);
        $event->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeView(Event $event, $user): void
    {
        if ($event->user_id === $user->id) return;
        if ($event->is_company_event && in_array($event->visibility, ['company', 'group', 'public'])) return;
        abort(403);
    }

    private function authorizeEdit(Event $event, $user): void
    {
        if ($event->user_id === $user->id) return;
        if ($event->organizer_id === $user->id) return;
        abort(403);
    }
}
