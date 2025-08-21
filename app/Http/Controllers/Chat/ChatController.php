<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

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
            $item = [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user ? $msg->user->name : '',
                'created_at' => $msg->created_at,
                'is_read' => $isRead,
                'type' => $msg->type ?? 'text',
            ];
            if (($msg->type ?? 'text') === 'file') {
                $decoded = json_decode($msg->body, true);
                if (is_array($decoded)) {
                    $item['file'] = $this->sanitizeFileMeta($decoded) ?? null;
                    // set a friendly message field for backward compatibility
                    $item['message'] = ($item['file']['original_name'] ?? $decoded['original_name'] ?? '');
                } else {
                    $item['message'] = $msg->body;
                }
            } else {
                $item['message'] = $msg->body;
            }
            return $item;
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
            $item = [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user ? $msg->user->name : '',
                'created_at' => $msg->created_at,
                'type' => $msg->type ?? 'text',
            ];
            if (($msg->type ?? 'text') === 'file') {
                $decoded = json_decode($msg->body, true);
                if (is_array($decoded)) {
                    $item['file'] = $decoded;
                    $item['message'] = $decoded['original_name'] ?? '';
                } else {
                    $item['message'] = $msg->body;
                }
            } else {
                $item['message'] = $msg->body;
            }
            return $item;
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
        // Accept either text body or file upload. If file present, store it and create a file-type message
        $data = $request->validate([
            'body' => 'nullable|string|max:2000',
            'type' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // 10MB
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('chat', 'public');
            $url = Storage::url($path);
            $meta = [
                'url' => $url,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'path' => $path,
            ];
            // 画像ならサムネイルを作成（Intervention Image が無ければスキップ）
            if (str_starts_with($file->getClientMimeType(), 'image/')) {
                if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
                    try {
                        $img = \Intervention\Image\ImageManagerStatic::make($file->getRealPath());
                        $img->orientate();
                        $img->fit(400, 400, function ($constraint) {
                            $constraint->upsize();
                        });
                        $thumbPath = 'chat/thumbs/' . basename($path);
                        Storage::disk('public')->put($thumbPath, (string) $img->encode());
                        $meta['thumb_url'] = Storage::url($thumbPath);
                        $meta['thumb_path'] = $thumbPath;
                    } catch (\Exception $ex) {
                        // サムネ作成失敗しても本体は保存済みなので処理を続行
                        Log::warning('thumb create failed: ' . $ex->getMessage());
                    }
                } else {
                    Log::warning('Intervention Image not available; skipping thumbnail creation');
                }
            }

            $msg = $room->messages()->create([
                'user_id' => $user->id,
                'body' => json_encode($meta),
                'type' => 'file',
            ]);
        } else {
            $data = $request->validate([
                'body' => 'required|string|max:2000',
                'type' => 'nullable|string',
            ]);
            $msg = $room->messages()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
                'type' => $data['type'] ?? 'text',
            ]);
        }
        // ルーム全体にリアルタイム配信
        event(new \App\Events\ChatMessageSent($msg));

        $response = [
            'id' => $msg->id,
            'user_id' => $msg->user_id,
            'user_name' => $user->name,
            'message' => $msg->body,
            'type' => $msg->type,
            'created_at' => $msg->created_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
        ];
        if ($msg->type === 'file') {
            $decoded = json_decode($msg->body, true);
            if (is_array($decoded)) {
                $response['file'] = $this->sanitizeFileMeta($decoded) ?? null;
                // 表示用メッセージは元のファイル名を使う
                $response['message'] = ($response['file']['original_name'] ?? $decoded['original_name'] ?? $response['message']);
            } else {
                // もし body が生のテキストのままならそのまま表示
                $response['message'] = $msg->body;
            }
        }

        return response()->json($response);
    }

    // ストレージ内の添付ファイルを安全に配信するエンドポイント
    // public/storage/chat 以下のファイルを返す。ルーム参加チェックや追加の認可をここで行える。
    public function streamAttachment(Request $request)
    {
        $user = $request->user();
        // クエリパラメータ ?path=chat/xxx を期待
        $path = $request->query('path');
        if (!$path) {
            abort(400, 'path is required');
        }
        // 危険なパスを回避
        $path = ltrim($path, '\/');
        // only allow files under chat/ inside public disk
        if (!str_starts_with($path, 'chat/')) {
            // 直書きの場合は chat/ を補う
            $path = 'chat/' . $path;
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // 追加の認可チェック: ファイルを投稿したメッセージのルームにユーザが参加しているか
        try {
            // find message by body containing path (best-effort)
            $escaped = addcslashes($path, '\"');
            $message = \App\Models\ChatMessage::where('type', 'file')
                ->where('body', 'like', '%"path":"' . $escaped . '%')
                ->first();
            if ($message) {
                $room = $message->room;
                if ($room && !$room->users->contains($user->id)) {
                    abort(403, 'このファイルにアクセスする権限がありません');
                }
            }
        } catch (\Exception $ex) {
            // 認可チェックで失敗しても、存在する場合は引き続き配信する（堅牢性優先）
            Log::warning('attachment auth check failed: '.$ex->getMessage());
        }

        // フルパスを取得してファイルを返す（ブラウザは Content-Type に応じて表示する）
        $full = Storage::disk('public')->path($path);
        return response()->file($full);
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

    protected function sanitizeFileMeta(array $fileMeta)
    {
        $out = [];
        if (isset($fileMeta['original_name'])) $out['original_name'] = substr($fileMeta['original_name'], 0, 255);
        if (isset($fileMeta['mime'])) $out['mime'] = substr($fileMeta['mime'], 0, 100);
        if (isset($fileMeta['size'])) $out['size'] = intval($fileMeta['size']);
        if (!empty($fileMeta['path'])) {
            $p = ltrim($fileMeta['path'], '\/');
            if (Str::startsWith($p, ['chat/', 'bot/', 'attachments/'])) {
                $out['path'] = $p;
                $out['streamUrl'] = Storage::url($p);
                if (!Storage::disk('public')->exists($p)) return null;
                return $out;
            }
        }
        if (!empty($fileMeta['url']) && filter_var($fileMeta['url'], FILTER_VALIDATE_URL)) {
            try {
                $host = parse_url($fileMeta['url'], PHP_URL_HOST);
                $appHost = parse_url(config('app.url') ?? URL::to('/'), PHP_URL_HOST);
                if ($host === $appHost || $host === null) {
                    $out['url'] = $fileMeta['url'];
                    return $out;
                }
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}
