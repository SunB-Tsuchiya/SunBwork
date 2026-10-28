<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamCalendarColor;
use App\Services\ClerkCalendarHolidays;
use App\Support\CalendarColors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * チームルームのカレンダー: 色設定（チームごと。メンバー全員が変更できる）と、祝日（会社の休日カレンダーの読み取り）。
 */
class TeamCalendarController extends Controller
{
    public function colors(Team $team)
    {
        $this->assertMember($team);

        return response()->json(CalendarColors::listFor(TeamCalendarColor::class, 'team_id', $team->id));
    }

    public function updateColor(Request $request, Team $team, string $colorKey)
    {
        $this->assertMember($team);
        $validated = $request->validate(['label' => 'nullable|string|max:20']);

        return response()->json(CalendarColors::updateLabel(TeamCalendarColor::class, 'team_id', $team->id, $colorKey, $validated['label'] ?? null, Auth::id()));
    }

    public function reorderColors(Request $request, Team $team)
    {
        $this->assertMember($team);
        $request->validate([
            'orders'              => 'required|array',
            'orders.*.color_key'  => 'required|string|max:20',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        return response()->json(CalendarColors::reorder(TeamCalendarColor::class, 'team_id', $team->id, $request->orders, Auth::id()));
    }

    /** チームの会社の祝日（年の前後を含む）。会社が無いチームは空。DB は書き換えない */
    public function holidays(Request $request, Team $team, ClerkCalendarHolidays $holidays)
    {
        $this->assertMember($team);
        $request->validate(['year' => 'sometimes|required|integer|min:1900|max:2100']);
        $year = (int) $request->input('year', now('Asia/Tokyo')->year);

        return response()->json($team->company_id ? $holidays->forCompany((int) $team->company_id, $year) : []);
    }

    private function assertMember(Team $team): void
    {
        app(TeamRoomController::class)->assertMember($team);
    }
}
