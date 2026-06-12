<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MeetingRoomController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $rooms = MeetingRoom::where('company_id', $user->company_id)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/MeetingRooms/Index', ['rooms' => $rooms]);
    }

    public function create()
    {
        return Inertia::render('Admin/MeetingRooms/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'capacity'    => 'nullable|integer|min:1|max:999',
            'description' => 'nullable|string',
            'color'       => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'active'      => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $user = Auth::user();
        MeetingRoom::create(['company_id' => $user->company_id, ...$validated]);

        return redirect()->route('admin.meeting-rooms.index')
            ->with('success', '会議室を登録しました');
    }

    public function edit(MeetingRoom $room)
    {
        return Inertia::render('Admin/MeetingRooms/Edit', ['room' => $room]);
    }

    public function update(Request $request, MeetingRoom $room)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'capacity'    => 'nullable|integer|min:1|max:999',
            'description' => 'nullable|string',
            'color'       => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'active'      => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $room->update($validated);

        return redirect()->route('admin.meeting-rooms.index')
            ->with('success', '会議室を更新しました');
    }

    public function destroy(MeetingRoom $room)
    {
        $room->delete();

        return redirect()->route('admin.meeting-rooms.index')
            ->with('success', '会議室を削除しました');
    }
}
