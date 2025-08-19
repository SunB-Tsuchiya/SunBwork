<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        // 全ユーザー一覧（自分以外）を取得
        $user = $request->user();
        $users = User::where('id', '!=', $user->id)->get(['id', 'name', 'user_role', 'department_id', 'assignment_id']);
        return Inertia::render('Chat/Index', [
            'users' => $users,
        ]);
    }
    
    // ルーム内メッセージ履歴取得API
    public function roomMessages(Request $request, $roomId)
    {
        $user = $request->user();
        $room = \App\Models\ChatRoom::with(['messages.user'])->findOrFail($roomId);
        // 参加者でなければ403
        if (!$room->users->contains($user->id)) {
            abort(403, 'このルームの参加者のみ閲覧できます');
        }
        $user = $request->user();
        $messages = $room->messages->sortBy('created_at')->values()->map(function($msg) use ($user) {
            $isRead = $msg->reads()->where('user_id', $user->id)->whereNotNull('read_at')->exists();
            return [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user ? $msg->user->name : '',
                'message' => $msg->body,
                'created_at' => $msg->created_at,
                'is_read' => $isRead,
            ];
        });
        return response()->json($messages);
    }

        // ルーム一覧
    public function indexRooms(Request $request)
    {
        $user = $request->user();
        $rooms = \App\Models\ChatRoom::with(['users', 'messages.reads' => function($q) use ($user) {
            $q->where('user_id', $user->id);
        }])->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })->get();

        // 各ルームごとに未読件数を付与
        $rooms = $rooms->map(function($room) use ($user) {
            $unreadCount = $room->messages->filter(function($msg) use ($user) {
                // 自分以外のメッセージで、既読レコードがないもの
                return $msg->user_id !== $user->id && !$msg->reads->where('user_id', $user->id)->whereNotNull('read_at')->count();
            })->count();
            $room->unread_count = $unreadCount;
            return $room;
        });
        return inertia('Chat/Index', [
            'rooms' => $rooms,
            'auth' => [ 'user' => $user ],
        ]);
    }

    // ルーム作成画面
    public function createRoom(Request $request)
    {
        $users = \App\Models\User::select('id', 'name', 'department_id', 'assignment_id')->get();
        $departments = \App\Models\Department::select('id', 'name')->get();
        $assignments = \App\Models\Assignment::select('id', 'name', 'department_id')->get();
        $user = $request->user();
        return inertia('Chat/CreateRoom', [
            'members' => $users,
            'departments' => $departments,
            'assignments' => $assignments,
            'user' => $user,
        ]);
    }

    // ルーム作成
    public function storeRoom(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|string',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        $room = \App\Models\ChatRoom::create([
            'name' => $data['type'] === 'private' ? null : ($data['name'] ?? null),
            'type' => $data['type'],
        ]);
        $room->users()->attach($data['user_ids']);
        // フラッシュメッセージを付与してリダイレクト
        return redirect()->route('chat.rooms.index')->with('success', 'チャットルームを作成しました');
    }

    // ルーム詳細
    public function showRoom(Request $request, $id)
    {
        // ルーム情報とメッセージを取得
        $user = $request->user();
        $room = \App\Models\ChatRoom::with(['users', 'messages.user'])->findOrFail($id);
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
            'room' => $room ? $room->toArray() : [],
            'auth' => ['user' => $user],
            'messages' => $messages ?? [],
        ]);
    }

    // ルーム更新
    public function updateRoom(Request $request, $id)
    {
        $room = \App\Models\ChatRoom::findOrFail($id);
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
    public function destroyRoom($id)
    {
        $room = \App\Models\ChatRoom::findOrFail($id);
        $room->delete();
        return response()->json(['message' => 'deleted']);
    }

    // ルーム内メッセージ送信API
    public function sendRoomMessage(Request $request, $id)
    {
              
        $user = $request->user();
        $room = \App\Models\ChatRoom::findOrFail($id);
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'type' => 'nullable|string',
        ]);
        $msg = $room->messages()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'type' => $data['type'] ?? 'text',
        ]);
        // ルーム全体にリアルタイム配信
        event(new \App\Events\ChatMessageSent($msg));

        return response()->json([
            'id' => $msg->id,
            'user_id' => $msg->user_id,
            'user_name' => $user->name,
            'message' => $msg->body,
            'created_at' => $msg->created_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
        ]);
    }

    // メッセージ既読登録API
    public function markAsRead(Request $request, $messageId)
    {
        $user = $request->user();
        $message = \App\Models\ChatMessage::findOrFail($messageId);
        $read = \App\Models\ChatMessageRead::firstOrCreate([
            'chat_message_id' => $message->id,
            'user_id' => $user->id,
        ], [
            'read_at' => now(),
        ]);
        if (!$read->read_at) {
            $read->read_at = now();
            $read->save();
        }
        return response()->json(['status' => 'ok']);
    }
}
