<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Message;
use App\Models\User;

$id = $argv[1] ?? null;
if (!$id) {
    echo "Usage: php dump_message_id.php <id>\n";
    exit(1);
}

$user = User::find(1);
if ($user) auth()->setUser($user);

$message = Message::with(['fromUser', 'recipients.user', 'attachments'])->find($id);
if (!$message) {
    echo json_encode(['error' => 'message not found']);
    exit(0);
}

$mapped = $message->toArray();
$mapped['attachments'] = $message->attachments->map(function ($att) {
    $url = null;
    $public = null;
    if ($att->status === 'ready' && $att->path) {
        try {
            $url = route('api.attachments.stream', ['path' => $att->path]);
            $public = asset('storage/' . ltrim($att->path, '/'));
        } catch (\Throwable $__e) {
            $url = null;
            $public = null;
        }
    }
    return [
        'id' => $att->id,
        'original_name' => $att->original_name,
        'mime_type' => $att->mime_type,
        'size' => $att->size,
        'status' => $att->status,
        'url' => $url,
        'public_url' => $public,
        'path' => $att->path,
    ];
})->values();

echo json_encode($mapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
