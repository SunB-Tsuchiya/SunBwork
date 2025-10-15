<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Foundation\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiConversation;
use App\Models\AiMessage;

$conv = AiConversation::with('messages')->find(5);
if (!$conv) {
    echo json_encode(['error' => 'not found']);
    exit(0);
}
$arr = $conv->toArray();
// sanitize meta similar to AiHistoryController::showJson
if (!empty($arr['messages']) && is_array($arr['messages'])) {
    foreach ($arr['messages'] as &$m) {
        // only preserve file meta keys similar to sanitizeMeta behaviour
        if (!empty($m['meta']) && is_array($m['meta'])) {
            $meta = $m['meta'];
            if (isset($meta['file']) && is_array($meta['file'])) {
                $file = [];
                if (isset($meta['file']['original_name'])) $file['original_name'] = $meta['file']['original_name'];
                if (isset($meta['file']['mime'])) $file['mime'] = $meta['file']['mime'];
                if (isset($meta['file']['size'])) $file['size'] = intval($meta['file']['size']);
                if (isset($meta['file']['path'])) $file['path'] = $meta['file']['path'];
                if (isset($meta['file']['url'])) $file['url'] = $meta['file']['url'];
                $m['meta'] = ['file' => $file];
            } else {
                $m['meta'] = null;
            }
        }
    }
}
echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
