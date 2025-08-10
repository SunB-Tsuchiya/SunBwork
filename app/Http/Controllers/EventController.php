<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EventController extends Controller
{
    // ユーザーの予定一覧取得（カレンダー表示用）
    public function index()
    {
        $events = Event::where('user_id', Auth::id())->get();
        return response()->json($events);
    }

    // 予定の新規作成
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date',
            'end' => 'nullable|date',
        ]);
        $data['user_id'] = Auth::id();
        $event = Event::create($data);
        return response()->json($event, 201);
    }

    // 予定の更新
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date',
            'end' => 'nullable|date',
        ]);
        $event->update($data);
        return response()->json($event);
    }

    // 予定の削除
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        $event->delete();
        return response()->json(['message' => 'deleted']);
    }
}
