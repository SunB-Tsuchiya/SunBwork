<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Leader\MeetingDefinitionController as LeaderMeetingDefinitionController;
use App\Models\MeetingDefinition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Admin用会議設定Controller。
 * Leader版と同一ロジック。メンバーの取得範囲のみ全ユーザーに変更。
 * index/create/edit のレンダリング先を Admin 向けに変更。
 */
class MeetingDefinitionController extends LeaderMeetingDefinitionController
{
    protected function getAvailableMembers(): \Illuminate\Support\Collection
    {
        return User::orderBy('name')->get(['id', 'name', 'department_id']);
    }

    public function index()
    {
        $meetingDefinitions = MeetingDefinition::where('created_by', Auth::id())
            ->with(['members:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/MeetingDefinitions/Index', [
            'meetingDefinitions' => $meetingDefinitions,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/MeetingDefinitions/Create', [
            'availableMembers' => $this->getAvailableMembers(),
        ]);
    }

    public function store(Request $request)
    {
        // Leader の store ロジックを再利用し、リダイレクト先だけ admin ルートに変更
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

        return redirect()->route('admin.meeting_definitions.index')
            ->with('success', '会議を登録しました。');
    }

    public function edit(MeetingDefinition $meetingDefinition)
    {
        if ($meetingDefinition->created_by !== Auth::id()) {
            abort(403);
        }
        $meetingDefinition->load('members:id,name');

        return Inertia::render('Admin/MeetingDefinitions/Edit', [
            'meetingDefinition' => $meetingDefinition,
            'availableMembers'  => $this->getAvailableMembers(),
        ]);
    }

    public function update(Request $request, MeetingDefinition $meetingDefinition)
    {
        if ($meetingDefinition->created_by !== Auth::id()) {
            abort(403);
        }

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

        return redirect()->route('admin.meeting_definitions.index')
            ->with('success', '会議を更新しました。');
    }

    public function destroy(MeetingDefinition $meetingDefinition)
    {
        if ($meetingDefinition->created_by !== Auth::id()) {
            abort(403);
        }

        $meetingDefinition->members()->detach();
        $meetingDefinition->delete();

        return redirect()->route('admin.meeting_definitions.index')
            ->with('success', '会議を削除しました。');
    }
}
