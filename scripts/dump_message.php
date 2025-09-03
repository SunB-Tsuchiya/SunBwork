<?php
require __DIR__ . '/../vendor/autoload.php';

// bootstrap framework
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Message;
use App\Models\User;

// authenticate as user id 1 if present
$user = User::find(1);
if ($user) {
    auth()->setUser($user);
}

$message = Message::with(['fromUser', 'recipients.user', 'attachments'])->find(4);
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
            // route/asset may fail in CLI if URL generator not configured; ignore
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

echo json_encode($mapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
