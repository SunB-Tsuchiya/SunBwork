<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatRoomController extends Controller
{
    // ルーム一覧
    public function index()
    {
    $user = Auth::user();
        $rooms = ChatRoom::with('users')
            ->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->get();
        return inertia('Chat/Index', [
            'rooms' => $rooms,
            'auth' => [ 'user' => $user ],
        ]);
    }

    // ルーム作成画面
    public function create()
    {
        $users = \App\Models\User::select('id', 'name', 'department_id', 'assignment_id')->get();
        $departments = \App\Models\Department::select('id', 'name')->get();
        $assignments = \App\Models\Assignment::select('id', 'name', 'department_id')->get();
    $user = Auth::user();
        return inertia('Chat/CreateRoom', [
            'members' => $users,
            'departments' => $departments,
            'assignments' => $assignments,
            'user' => $user,
        ]);
    }

    // ルーム作成
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|string',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        $room = ChatRoom::create([
            'name' => $data['type'] === 'private' ? null : ($data['name'] ?? null),
            'type' => $data['type'],
        ]);
        $room->users()->attach($data['user_ids']);
        // フラッシュメッセージを付与してリダイレクト
        return redirect()->route('chat.index')->with('success', 'チャットルームを作成しました');
    }

    // ルーム詳細
    public function show($id)
    {
        $user = Auth::user();
        $room = ChatRoom::with(['users', 'messages.user'])->findOrFail($id);
        // メッセージは新しい順で渡す（bodyはモデルで自動復号）
        $messages = $room->messages->sortBy('created_at')->values()->map(function($msg) {
            return [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user ? $msg->user->name : '',
                'message' => $msg->body,
                'created_at' => $msg->created_at,
            ];
        });
        return inertia('Chat/ChatRoom', [
            'room' => $room,
            'auth' => ['user' => $user],
            'messages' => $messages,
        ]);
    }

    // ルーム更新
    public function update(Request $request, $id)
    {
        $room = ChatRoom::findOrFail($id);
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        $room->update([
            'name' => $data['name'] ?? $room->name,
            'type' => $data['type'] ?? $room->type,
        ]);
        if (isset($data['user_ids'])) {
            $room->users()->sync($data['user_ids']);
        }
        return response()->json($room->load('users'));
    }

    // ルーム削除
    public function destroy($id)
    {
        $room = ChatRoom::findOrFail($id);
        $room->delete();
        return response()->json(['message' => 'deleted']);
    }
}
