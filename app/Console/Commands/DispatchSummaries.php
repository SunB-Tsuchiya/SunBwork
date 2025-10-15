<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiConversation;
use App\Models\AiSummaryJob;
use App\Jobs\SummarizeConversationJob;
use Illuminate\Support\Facades\Log;

class DispatchSummaries extends Command
{
    protected $signature = 'ai:dispatch-summaries {--dry-run}';
    protected $description = 'Dispatch summarization jobs for conversations that exceed configured thresholds.';

    public function handle()
    {
        $dry = $this->option('dry-run');
        $charThreshold = config('ai_summary.char_threshold', 20000);
        $msgThreshold = config('ai_summary.message_threshold', 200);
        $cooldown = config('ai_summary.dispatch_cooldown_minutes', 10);

        $this->info("Scanning conversations for summarization (char > {$charThreshold} or messages > {$msgThreshold})");

        // Find conversations where chars since last summary or message count since last summary exceed thresholds
        $convs = AiConversation::with(['messages'])->get();
        $now = now();
        $dispatched = 0;

        foreach ($convs as $conv) {
            $lastSummary = $conv->summary?->summarized_until_message_id ?? 0;
            // messages after last summary
            $after = $conv->messages->filter(function ($m) use ($lastSummary) {
                return $m->id > $lastSummary;
            });
            $chars = $after->reduce(function ($carry, $m) {
                return $carry + (int)($m->char_count ?? mb_strlen($m->content ?? ''));
            }, 0);
            $count = $after->count();

            // ensure cooldown: do not dispatch if a recent job exists
            $recentJob = AiSummaryJob::where('ai_conversation_id', $conv->id)
                ->where('created_at', '>=', $now->subMinutes($cooldown))
                ->exists();
            if ($recentJob) continue;

            if ($chars >= $charThreshold || $count >= $msgThreshold) {
                $this->line("Queuing summary for conversation {$conv->id} (chars={$chars}, msgs={$count})");
                if (!$dry) {
                    // create a job record and dispatch
                    $job = AiSummaryJob::create([
                        'ai_conversation_id' => $conv->id,
                        'status' => 'pending',
                        'message_count' => $count,
                        'chars_processed' => $chars,
                    ]);
                    SummarizeConversationJob::dispatch($conv->id, config('ai_summary.max_messages_per_job', 500));
                    $dispatched++;
                }
            }
        }

        $this->info("Dispatch complete. Jobs dispatched: {$dispatched}");
        return 0;
    }
}
