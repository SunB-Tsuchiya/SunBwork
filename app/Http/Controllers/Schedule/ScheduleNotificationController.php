<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\ScheduleNotification;
use Illuminate\Support\Facades\Auth;

class ScheduleNotificationController extends Controller
{
    public function index()
    {
        $notifications = ScheduleNotification::where('user_id', Auth::id())
            ->with(['event:id,title,starts_at,ends_at', 'fromUser:id,name'])
            ->orderByDesc('scheduled_at')
            ->limit(50)
            ->get();

        return response()->json($notifications);
    }

    public function read(ScheduleNotification $notification)
    {
        if ($notification->user_id !== Auth::id()) abort(403);

        $notification->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
