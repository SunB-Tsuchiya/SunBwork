<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\ScheduleCalendarOverlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleOverlayController extends Controller
{
    public function index()
    {
        $overlays = ScheduleCalendarOverlay::where('user_id', Auth::id())
            ->with(['targetUser:id,name', 'targetCompany:id,name', 'targetDepartment:id,name'])
            ->orderBy('sort_order')
            ->get();

        return response()->json($overlays);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_user_id'       => 'nullable|exists:users,id',
            'target_company_id'    => 'nullable|exists:companies,id',
            'target_department_id' => 'nullable|exists:departments,id',
        ]);

        // target_* はいずれか1つのみ非null
        $set = array_filter($validated, fn ($v) => $v !== null);
        if (count($set) !== 1) {
            abort(422, 'target_user_id / target_company_id / target_department_id のいずれか1つを指定してください');
        }

        $maxOrder = ScheduleCalendarOverlay::where('user_id', Auth::id())->max('sort_order') ?? 0;

        $overlay = ScheduleCalendarOverlay::create([
            'user_id'    => Auth::id(),
            'sort_order' => $maxOrder + 1,
            ...$validated,
        ]);

        $overlay->load(['targetUser:id,name', 'targetCompany:id,name', 'targetDepartment:id,name']);

        return response()->json($overlay, 201);
    }

    public function destroy(ScheduleCalendarOverlay $overlay)
    {
        if ($overlay->user_id !== Auth::id()) abort(403);
        $overlay->delete();

        return response()->json(['ok' => true]);
    }
}
