<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ScheduleAttendee;
use App\Models\SchedulePermissionSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleAttendeeController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $actor = Auth::user();
        $this->checkPermission($actor, $event);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $attendee = ScheduleAttendee::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $request->user_id],
            ['status' => 'pending', 'added_by' => $actor->id]
        );

        return response()->json($attendee->load('user:id,name'), 201);
    }

    public function destroy(Event $event, User $user)
    {
        $actor = Auth::user();

        // 自分自身の削除 or 権限あり
        if ($actor->id !== $user->id) {
            $this->checkPermission($actor, $event);
        }

        ScheduleAttendee::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function checkPermission($actor, Event $event): void
    {
        $setting = SchedulePermissionSetting::where('company_id', $actor->company_id)->first();
        $minRole = $setting->can_add_to_others_min_role ?? 'coordinator';

        $roleOrder = ['user' => 0, 'coordinator' => 1, 'leader' => 2, 'clerk' => 2, 'admin' => 3, 'superadmin' => 4];
        $actorLevel = $roleOrder[$actor->user_role] ?? 0;
        $minLevel   = $roleOrder[$minRole] ?? 1;

        if ($actorLevel < $minLevel) {
            abort(403, '他者の予定に追加する権限がありません');
        }
    }
}
