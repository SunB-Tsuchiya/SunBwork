<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AiConversation;
use App\Models\AiSummary;
use App\Models\AiMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SummarizeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $conversationId;
    public $maxMessages = 500;

    public function __construct($conversationId, $maxMessages = 500)
    {
        $this->conversationId = $conversationId;
        $this->maxMessages = $maxMessages;
    }

    public function handle()
    {
        // This is a skeleton: gather messages and call OpenAI (or other summarizer) to get a summary.
        $conv = AiConversation::find($this->conversationId);
        if (!$conv) return;

        // fetch messages newer than last summarized id
        $lastSumm = AiSummary::where('ai_conversation_id', $conv->id)->orderBy('id', 'desc')->first();
        $lastId = $lastSumm ? $lastSumm->summarized_until_message_id : 0;

        $msgs = AiMessage::where('ai_conversation_id', $conv->id)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit($this->maxMessages)
            ->get();

        if ($msgs->isEmpty()) return;

        // build a compact text for summarization
        $text = '';
        foreach ($msgs as $m) {
            $role = $m->role;
            $text .= strtoupper($role) . ': ' . ($m->content ?? '') . "\n";
        }

        // Call OpenAI to generate a concise summary of the messages
        $openaiKey = env('VITE_OPENAI_API_KEY') ?: env('OPENAI_API_KEY');
        $summaryText = null;
        try {
            if (!$openaiKey) {
                Log::warning('SummarizeConversationJob: OpenAI key not configured');
            } else {
                // Build payload for summarization
                $system = "You are a summarization assistant. Produce a concise Japanese summary of the conversation, focusing on user intent, decisions, important facts and attached files. Keep it under 400 characters.";
                $msgsForOpenai = [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'system', 'content' => 'When listing files, prefer original file names and mention if content was not included.'],
                ];
                // append the messages chunk (may be large)
                foreach ($msgs as $m) {
                    $role = $m->role ?? 'user';
                    $content = $m->content ?? '';
                    $msgsForOpenai[] = ['role' => $role, 'content' => $content];
                }

                $payload = [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => $msgsForOpenai,
                    'temperature' => 0.3,
                    'max_tokens' => 500,
                ];

                $resp = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $openaiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/chat/completions', $payload);

                if ($resp->successful()) {
                    $j = $resp->json();
                    if (is_array($j) && isset($j['choices'][0]['message']['content'])) {
                        $summaryText = trim($j['choices'][0]['message']['content']);
                    }
                } else {
                    Log::warning('SummarizeConversationJob OpenAI non-success', ['status' => $resp->status(), 'body' => $resp->body()]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SummarizeConversationJob OpenAI call failed: ' . $e->getMessage());
        }

        if (empty($summaryText)) {
            // fallback to simple trimmed placeholder
            $summaryText = '自動要約（プレースホルダ）: ' . mb_substr(strip_tags($text), 0, 400);
        }

        $s = AiSummary::create([
            'ai_conversation_id' => $conv->id,
            'summary' => $summaryText,
            'summarized_until_message_id' => $msgs->last()->id,
            'char_count' => mb_strlen($text),
            'version' => ($lastSumm ? $lastSumm->version + 1 : 1),
        ]);

        // Attach summary id to conversation for quick access
        $conv->summary_id = $s->id;
        $conv->save();
    }
}
