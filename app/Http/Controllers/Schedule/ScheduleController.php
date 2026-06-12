<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\EventItemType;
use App\Models\MeetingRoom;
use App\Models\ScheduleCalendarOverlay;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $overlays = ScheduleCalendarOverlay::where('user_id', $user->id)
            ->with(['targetUser:id,name', 'targetCompany:id,name', 'targetDepartment:id,name'])
            ->orderBy('sort_order')
            ->get();

        $rooms = MeetingRoom::where('company_id', $user->company_id)
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        $eventItemTypes = EventItemType::orderBy('id')->get(['id', 'name']);

        // 参加者ピッカー用: 全会社・全部署（company_id 付き）
        $companies   = Company::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('sort_order')->get(['id', 'name', 'company_id']);

        return Inertia::render('Schedule/Index', [
            'initialDate'    => now()->toDateString(),
            'overlays'       => $overlays,
            'rooms'          => $rooms,
            'eventItemTypes' => $eventItemTypes,
            'companies'      => $companies,
            'departments'    => $departments,
        ]);
    }

    public function rooms(Request $request)
    {
        $user = Auth::user();
        $rooms = MeetingRoom::where('company_id', $user->company_id)
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'capacity']);

        return response()->json($rooms);
    }

    public function users(Request $request)
    {
        $selfId    = Auth::id();
        $q         = trim($request->get('q', ''));
        $companyId = $request->get('company_id');
        $deptId    = $request->get('department_id');

        $users = User::where('id', '!=', $selfId)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($deptId,    fn ($query) => $query->where('department_id', $deptId))
            ->when($q !== '',  fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->get(['id', 'name', 'user_role', 'department_id', 'company_id']);

        return response()->json($users);
    }
}
