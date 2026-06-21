<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MeetingRoom;
use App\Models\RoomReservation;
use App\Models\ScheduleAttendee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleRoomReservationController extends Controller
{
    public function store(Request $request, MeetingRoom $room)
    {
        $this->authorizeRoom($room);

        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'starts_at'           => 'required|date',
            'ends_at'             => 'required|date|after:starts_at',
            'notes'               => 'nullable|string',
            'event_item_type_id'  => 'nullable|exists:event_item_types,id',
            'destination'         => 'nullable|string|max:255',
            'self_included'       => 'boolean',
            'attendee_ids'        => 'nullable|array',
            'attendee_ids.*'      => 'exists:users,id',
            'link_event_id'       => 'nullable|exists:events,id',
        ]);

        $user           = Auth::user();
        $selfIncluded   = (bool) ($validated['self_included'] ?? true);
        $attendeeIds    = array_map('intval', $validated['attendee_ids'] ?? []);

        $this->validateAttendeeScope($attendeeIds, $user);

        $participantIds = collect($attendeeIds);
        if ($selfIncluded) {
            $participantIds = $participantIds->prepend((int) $user->id);
        }
        $participantIds = $participantIds->unique()->filter()->values();

        if ($participantIds->isEmpty()) {
            abort(422, '参加者を1名以上選択してください');
        }

        $this->checkSameDay($validated['starts_at'], $validated['ends_at']);
        $this->checkAvailableHours($room, $validated['starts_at'], $validated['ends_at']);

        $reservation = DB::transaction(function () use ($room, $validated, $user, $participantIds) {
            $this->checkConflict($room->id, $validated['starts_at'], $validated['ends_at']);

            $linkEventId = $validated['link_event_id'] ?? null;

            if ($linkEventId) {
                // 既存の予定に会議室予約をリンクする
                $event = Event::findOrFail($linkEventId);
                if ($event->user_id !== $user->id && $event->organizer_id !== $user->id) {
                    abort(403, '指定された予定へのアクセス権がありません');
                }
                if ($event->room_reservation_id) {
                    abort(422, 'この予定はすでに会議室予約に紐づいています');
                }
                $event->update([
                    'title'              => $validated['title'],
                    'starts_at'          => $validated['starts_at'],
                    'ends_at'            => $validated['ends_at'],
                    'event_item_type_id' => $validated['event_item_type_id'] ?? $event->event_item_type_id,
                    'destination'        => $validated['destination'] ?? $event->destination,
                    'is_company_event'   => true,
                    'visibility'         => 'company',
                ]);

                // attendee_ids が明示的に送られた場合のみ差分更新する
                // EventModal からの link_event_id 経由では attendee_ids を送らないため
                // EventController が設定した参加者をそのまま保持する
                if (array_key_exists('attendee_ids', $validated)) {
                    $existingIds = ScheduleAttendee::where('event_id', $event->id)
                        ->pluck('user_id')
                        ->map(fn($id) => (int) $id)
                        ->values();
                    $toRemove = $existingIds->diff($participantIds);
                    if ($toRemove->isNotEmpty()) {
                        ScheduleAttendee::where('event_id', $event->id)
                            ->whereIn('user_id', $toRemove)
                            ->delete();
                    }
                    $toAdd = $participantIds->diff($existingIds);
                    foreach ($toAdd as $uid) {
                        ScheduleAttendee::create([
                            'event_id' => $event->id,
                            'user_id'  => $uid,
                            'status'   => $uid === (int) $user->id ? 'accepted' : 'pending',
                            'added_by' => $user->id,
                        ]);
                    }
                }
            } else {
                $event = Event::create([
                    'user_id'             => $user->id,
                    'title'               => $validated['title'],
                    'starts_at'           => $validated['starts_at'],
                    'ends_at'             => $validated['ends_at'],
                    'event_item_type_id'  => $validated['event_item_type_id'] ?? null,
                    'destination'         => $validated['destination'] ?? null,
                    'is_company_event'    => true,
                    'visibility'          => 'company',
                ]);

                foreach ($participantIds as $uid) {
                    ScheduleAttendee::create([
                        'event_id' => $event->id,
                        'user_id'  => $uid,
                        'status'   => $uid === (int) $user->id ? 'accepted' : 'pending',
                        'added_by' => $user->id,
                    ]);
                }
            }

            $reservation = RoomReservation::create([
                'meeting_room_id' => $room->id,
                'user_id'         => $user->id,
                'event_id'        => $event->id,
                'event_owned'     => !$linkEventId, // 新規作成 = true / リンク = false
                'title'           => $validated['title'],
                'starts_at'       => $validated['starts_at'],
                'ends_at'         => $validated['ends_at'],
                'notes'           => $validated['notes'] ?? null,
            ]);

            $event->update(['room_reservation_id' => $reservation->id]);

            return $reservation;
        });

        return response()->json($reservation->load('meetingRoom:id,name,color'), 201);
    }

    public function update(Request $request, RoomReservation $reservation)
    {
        $this->authorizeActor($reservation);

        $validated = $request->validate([
            'title'              => 'sometimes|string|max:255',
            'starts_at'          => 'sometimes|date',
            'ends_at'            => 'sometimes|date|after:starts_at',
            'notes'              => 'nullable|string',
            'event_item_type_id' => 'nullable|exists:event_item_types,id',
            'destination'        => 'nullable|string|max:255',
            'self_included'      => 'boolean',
            'attendee_ids'       => 'nullable|array',
            'attendee_ids.*'     => 'exists:users,id',
        ]);

        $user   = Auth::user();
        $starts = $validated['starts_at'] ?? $reservation->starts_at;
        $ends   = $validated['ends_at']   ?? $reservation->ends_at;

        // H-3: starts/ends の一方だけ更新されるケースの明示的チェック
        if (Carbon::parse($starts)->gte(Carbon::parse($ends))) {
            abort(422, '終了時刻は開始時刻より後に設定してください');
        }

        if (array_key_exists('attendee_ids', $validated)) {
            $validated['attendee_ids'] = array_map('intval', $validated['attendee_ids'] ?? []);
            $this->validateAttendeeScope($validated['attendee_ids'], $user);
        }

        $this->checkSameDay($starts, $ends);
        $this->checkAvailableHours($reservation->meetingRoom, $starts, $ends);

        DB::transaction(function () use ($reservation, $validated, $user, $starts, $ends) {
            $this->checkConflict($reservation->meeting_room_id, $starts, $ends, $reservation->id);

            $reservationFields = [];
            foreach (['title', 'starts_at', 'ends_at', 'notes'] as $f) {
                if (array_key_exists($f, $validated)) {
                    $reservationFields[$f] = $validated[$f];
                }
            }
            $reservation->update($reservationFields);

            if ($reservation->event_id && $event = Event::find($reservation->event_id)) {
                $eventFields = [];
                foreach (['title', 'starts_at', 'ends_at', 'event_item_type_id', 'destination'] as $f) {
                    if (array_key_exists($f, $validated)) {
                        $eventFields[$f] = $validated[$f];
                    }
                }
                if ($eventFields) {
                    $event->update($eventFields);
                }

                if (array_key_exists('attendee_ids', $validated) || array_key_exists('self_included', $validated)) {
                    $selfIncluded   = (bool) ($validated['self_included'] ?? true);
                    $participantIds = collect($validated['attendee_ids'] ?? [])->map(fn($id) => (int) $id);
                    if ($selfIncluded) {
                        $participantIds = $participantIds->prepend((int) $user->id);
                    }
                    $participantIds = $participantIds->unique()->filter()->values();

                    if ($participantIds->isEmpty()) {
                        abort(422, '参加者を1名以上選択してください');
                    }

                    // 差分更新: 既存ステータスを保持したまま追加・削除だけ行う
                    $existingIds = ScheduleAttendee::where('event_id', $event->id)
                        ->pluck('user_id')
                        ->map(fn($id) => (int) $id)
                        ->values();
                    $toRemove = $existingIds->diff($participantIds);
                    if ($toRemove->isNotEmpty()) {
                        ScheduleAttendee::where('event_id', $event->id)
                            ->whereIn('user_id', $toRemove)
                            ->delete();
                    }
                    $toAdd = $participantIds->diff($existingIds);
                    foreach ($toAdd as $uid) {
                        ScheduleAttendee::create([
                            'event_id' => $event->id,
                            'user_id'  => $uid,
                            'status'   => $uid === (int) $user->id ? 'accepted' : 'pending',
                            'added_by' => $user->id,
                        ]);
                    }
                }
            }
        });

        return response()->json($reservation->load('meetingRoom:id,name,color'));
    }

    public function destroy(RoomReservation $reservation)
    {
        $this->authorizeActor($reservation);

        DB::transaction(function () use ($reservation) {
            if ($reservation->event_id && $event = Event::find($reservation->event_id)) {
                if ((int) $event->room_reservation_id === $reservation->id) {
                    if ($reservation->event_owned) {
                        // 予約と同時に作成したイベントは一緒に削除する
                        ScheduleAttendee::where('event_id', $event->id)->delete();
                        $event->delete();
                    } else {
                        // 既存イベントをリンクした場合はリンクを解除するだけ
                        $event->update(['room_reservation_id' => null]);
                    }
                }
            }
            $reservation->delete();
        });

        return response()->json(['ok' => true]);
    }

    private function checkSameDay($starts, $ends): void
    {
        $sDate = Carbon::parse($starts)->toDateString();
        $eDate = Carbon::parse($ends)->toDateString();
        if ($sDate !== $eDate) {
            abort(422, '会議室予約は日をまたいで設定できません');
        }
    }

    private function checkAvailableHours(MeetingRoom $room, $starts, $ends): void
    {
        if (!$room->available_from || !$room->available_to) {
            return;
        }
        // 開始時刻のみチェック: 開始が予約可能範囲内であれば終了が超えていても許可
        // 例: available_to=17:00 のとき 16:00-18:00 は OK、17:00-18:00 は NG
        $sTime = Carbon::parse($starts)->format('H:i:s');
        if ($sTime < $room->available_from || $sTime >= $room->available_to) {
            abort(422, "この会議室の予約可能時間は {$room->available_from}〜{$room->available_to} 開始まで有効です");
        }
    }

    private function checkConflict(int $roomId, $starts, $ends, ?int $excludeId = null): void
    {
        $query = RoomReservation::where('meeting_room_id', $roomId)
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            ->lockForUpdate();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            abort(422, 'この時間帯は既に予約されています');
        }
    }

    private function authorizeRoom(MeetingRoom $room): void
    {
        $user = Auth::user();
        if ($room->company_id !== $user->company_id) {
            abort(403, 'この会議室を予約する権限がありません');
        }
        if (!$room->active) {
            abort(422, 'この会議室は現在利用できません');
        }
    }

    private function authorizeActor(RoomReservation $reservation): void
    {
        $user = Auth::user();
        if ($reservation->user_id === $user->id) return;
        if ($user->isSuperAdmin()) return;
        // M-4: Admin は自社の会議室の予約のみ管理可能
        if ($user->isAdmin()) {
            $roomCompanyId = (int) DB::table('meeting_rooms')
                ->where('id', $reservation->meeting_room_id)
                ->value('company_id');
            if ($roomCompanyId === (int) $user->company_id) return;
        }
        abort(403);
    }

    // H-1: attendee_ids のテナントスコープチェック（同社またはグループ会社のみ許可）
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
}
