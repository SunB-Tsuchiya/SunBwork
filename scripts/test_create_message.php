<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $u = App\Models\User::find(9);
    if (!$u) {
        echo "No user with id 9\n";
        exit(1);
    }
    $r = App\Models\ChatRoom::first();
    if (!$r) {
        echo "No chat room found\n";
        exit(1);
    }
    $m = $r->messages()->create([
        'user_id' => $u->id,
        'body' => 'テスト送信 from migration',
        'type' => 'text',
    ]);
    echo "Created message:\n";
    echo json_encode($m->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo "\n";
} catch (Throwable $e) {
    echo "Exception: ", get_class($e), "\n";
    echo $e->getMessage(), "\n";
    echo $e->getTraceAsString(), "\n";
    exit(1);
}
