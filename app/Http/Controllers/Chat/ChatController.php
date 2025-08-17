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
    
    public function messages(Request $request, $userId)
    {
        try {
            $authId = $request->user()->id;
            if (!\App\Models\User::find($userId)) {
                return response()->json(['error' => 'ユーザーが存在しません'], 404);
            }
            $messages = \App\Models\ChatMessage::where(function($q) use ($authId, $userId) {
                $q->where('from_user_id', $authId)->where('to_user_id', $userId);
            })->orWhere(function($q) use ($authId, $userId) {
                $q->where('from_user_id', $userId)->where('to_user_id', $authId);
            })->orderBy('created_at')->get();
            return response()->json($messages);
        } catch (\Exception $e) {
            return response()->json(['error' => '履歴取得エラー: ' . $e->getMessage()], 500);
        }
    }

    // チャット送信API
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'to_user_id' => 'required|exists:users,id',
                'body' => 'required|string|max:2000',
            ]);
            if ($request->user()->id == $request->to_user_id) {
                return response()->json(['error' => '自分自身には送信できません'], 400);
            }
            $message = \App\Models\ChatMessage::create([
                'from_user_id' => $request->user()->id,
                'to_user_id' => $request->to_user_id,
                'body' => $request->body,
                'type' => 'text',
            ]);
            // イベント発火
            event(new \App\Events\ChatMessageSent($message));
            return response()->json($message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'バリデーションエラー', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => '送信エラー: ' . $e->getMessage()], 500);
        }
    }
}
