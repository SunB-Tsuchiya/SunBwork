<?php
// Debug helper: run indexRooms-like logic for user id provided as first argument (default 9)
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use App\Models\ChatRoom;
use App\Models\User;

$userId = isset($argv[1]) ? intval($argv[1]) : 9;
$user = User::find($userId);
if (!$user) {
    echo json_encode(['error' => 'user not found', 'user_id' => $userId]);
    exit(1);
}

try {
    if (!Schema::hasTable('chat_rooms') && !Schema::hasTable('chats')) {
        $rooms = collect();
    } else {
        $rooms = ChatRoom::with(['users', 'messages.reads' => function ($q) use ($user) {
            $q->where('user_id', $user->id);
        }])->whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->get();
    }
} catch (\Throwable $e) {
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
    exit(1);
}

$rooms = $rooms->map(function ($room) use ($user) {
    $unreadCount = $room->messages->filter(function ($msg) use ($user) {
        return $msg->user_id !== $user->id && !$msg->reads->where('user_id', $user->id)->whereNotNull('read_at')->count();
    })->count();
    $room->unread_count = $unreadCount;
    return $room;
});

echo json_encode($rooms->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
