<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Foundation\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiSetting;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSummary;

$data = ['message' => 'テスト payload', 'conversation_id' => 5];
$messages = [];
$totalCharsIncluded = 0;
$setting = AiSetting::latest()->first();
$defaultSystem = 'You are a helpful assistant. Reply concisely in Japanese when appropriate.';
$system = $defaultSystem;
if ($setting && isset($setting->system_prompt) && $setting->system_prompt) {
    $system = $setting->system_prompt;
}
$messages[] = ['role' => 'system', 'content' => $system];
$messages[] = ['role' => 'user', 'content' => $data['message']];

$summaryContent = null;
if (!empty($data['conversation_id'])) {
    $convId = $data['conversation_id'];
    try {
        $convModel = AiConversation::find($convId);
        if ($convModel && !empty($convModel->summary_id)) {
            $summary = AiSummary::find($convModel->summary_id);
            if ($summary && !empty($summary->summary)) {
                $summaryContent = "Conversation summary (up to message #{$summary->summarized_until_message_id}):\n" . $summary->summary;
            }
        }
    } catch (\Throwable $_e) {
        // ignore
    }

    $historyCharBudget = 40000;
    $accum = 0;
    $toInclude = [];
    $batchSize = 50;
    $lastId = null;
    $done = false;
    while (!$done) {
        $q = AiMessage::where('ai_conversation_id', $convId)->select(['id','role','content','char_count'])->orderBy('id','desc')->limit($batchSize);
        if ($lastId) $q->where('id', '<', $lastId);
        $batch = $q->get();
        if ($batch->isEmpty()) break;
        foreach ($batch as $m) {
            $content = $m->content ?? '';
            if ($content === null || trim($content) === '') continue;
            $len = (isset($m->char_count) && $m->char_count) ? (int)$m->char_count : mb_strlen($content);
            if ($accum + $len > $historyCharBudget) { $done = true; break; }
            $toInclude[] = ['role'=>($m->role ?? 'user'),'content'=>$content,'id'=>$m->id,'len'=>$len];
            $accum += $len;
        }
        $lastId = $batch->last()->id;
        if ($batch->count() < $batchSize) break;
    }

    if (!empty($toInclude)) {
        $toInclude = array_reverse($toInclude);
        array_pop($messages);
        if (!empty($summaryContent)) {
            $messages[] = ['role' => 'system', 'content' => $summaryContent];
        }
        foreach ($toInclude as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content'], 'id' => $h['id'], 'len' => $h['len']];
        }
        $messages[] = ['role' => 'user', 'content' => $data['message']];
    }
}

echo json_encode($messages, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n";
