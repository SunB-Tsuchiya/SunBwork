<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Models\MeetingDefinition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MeetingDefinitionController extends Controller
{
    /** 対象メンバーの取得（部署リーダーまたは Admin/SuperAdmin: 全ユーザー、それ以外: 自部署のみ） */
    protected function getAvailableMembers(): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        if (in_array($user->user_role, ['admin', 'superadmin']) || $user->isDepartmentLeader()) {
            return User::orderBy('name')->get(['id', 'name', 'department_id', 'assignment_id']);
        }
        return User::where('department_id', $user->department_id)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'assignment_id']);
    }

    /** 部署一覧の取得（部署リーダー: 全部署、それ以外: 自部署のみ） */
    protected function getDepartments(): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        if ($user->isDepartmentLeader()) {
            return \App\Models\Department::orderBy('name')->get();
        }
        return \App\Models\Department::where('id', $user->department_id)->get();
    }

    public function index()
    {
        $meetingDefinitions = MeetingDefinition::where('created_by', Auth::id())
            ->with(['members:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Leader/MeetingDefinitions/Index', [
            'meetingDefinitions' => $meetingDefinitions,
        ]);
    }

    public function create()
    {
        return Inertia::render('Leader/MeetingDefinitions/Create', [
            'availableMembers' => $this->getAvailableMembers(),
            'departments'      => $this->getDepartments(),
            'assignments'      => \App\Models\Assignment::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'recurrence'    => 'required|in:weekly,biweekly,monthly',
            'day_of_week'   => 'required|integer|min:0|max:6',
            'week_of_month' => 'nullable|integer|min:1|max:5',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i|after:start_time',
            'members'       => 'required|array|min:1',
            'members.*'     => 'exists:users,id',
        ]);

        $def = MeetingDefinition::create([
            'created_by'    => Auth::id(),
            'title'         => $validated['title'],
            'description'   => $validated['description'] ?? null,
            'recurrence'    => $validated['recurrence'],
            'day_of_week'   => $validated['day_of_week'],
            'week_of_month' => $validated['recurrence'] === 'monthly' ? ($validated['week_of_month'] ?? null) : null,
            'start_time'    => $validated['start_time'],
            'end_time'      => $validated['end_time'],
        ]);
        $def->members()->sync($validated['members']);

        return redirect()->route('leader.meeting_definitions.index')
            ->with('success', '会議を登録しました。');
    }

    public function edit(MeetingDefinition $meetingDefinition)
    {
        $this->authorizeDefinition($meetingDefinition);

        $meetingDefinition->load('members:id,name');

        return Inertia::render('Leader/MeetingDefinitions/Edit', [
            'meetingDefinition' => $meetingDefinition,
            'availableMembers'  => $this->getAvailableMembers(),
            'departments'       => $this->getDepartments(),
            'assignments'       => \App\Models\Assignment::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MeetingDefinition $meetingDefinition)
    {
        $this->authorizeDefinition($meetingDefinition);

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'recurrence'    => 'required|in:weekly,biweekly,monthly',
            'day_of_week'   => 'required|integer|min:0|max:6',
            'week_of_month' => 'nullable|integer|min:1|max:5',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i|after:start_time',
            'members'       => 'required|array|min:1',
            'members.*'     => 'exists:users,id',
        ]);

        $meetingDefinition->update([
            'title'         => $validated['title'],
            'description'   => $validated['description'] ?? null,
            'recurrence'    => $validated['recurrence'],
            'day_of_week'   => $validated['day_of_week'],
            'week_of_month' => $validated['recurrence'] === 'monthly' ? ($validated['week_of_month'] ?? null) : null,
            'start_time'    => $validated['start_time'],
            'end_time'      => $validated['end_time'],
        ]);
        $meetingDefinition->members()->sync($validated['members']);

        return redirect()->route('leader.meeting_definitions.index')
            ->with('success', '会議を更新しました。');
    }

    public function destroy(MeetingDefinition $meetingDefinition)
    {
        $this->authorizeDefinition($meetingDefinition);

        $meetingDefinition->members()->detach();
        $meetingDefinition->delete();

        return redirect()->route('leader.meeting_definitions.index')
            ->with('success', '会議を削除しました。');
    }

    private function authorizeDefinition(MeetingDefinition $def): void
    {
        if ($def->created_by !== Auth::id()) {
            abort(403);
        }
    }
}
