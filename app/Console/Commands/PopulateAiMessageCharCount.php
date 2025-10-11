<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiMessage;

class PopulateAiMessageCharCount extends Command
{
    protected $signature = 'ai:populate-char-count {--batch=1000} {--sleep=0}';
    protected $description = 'Populate char_count column for existing ai_messages in batches';

    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $sleep = (int) $this->option('sleep');

        $this->info("Starting populate char_count for ai_messages in batches of {$batchSize}");

        $query = AiMessage::query()->where(function ($q) {
            $q->whereNull('char_count')->orWhere('char_count', 0);
        })->orderBy('id');

        $total = $query->count();
        $this->info("Total messages to process: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        while (true) {
            $batch = $query->limit($batchSize)->get();
            if ($batch->isEmpty()) break;
            foreach ($batch as $m) {
                $len = 0;
                try {
                    $len = mb_strlen($m->content ?? '');
                } catch (\Throwable $e) {
                    $len = strlen($m->content ?? '');
                }
                $m->char_count = $len;
                $m->save();
                $processed++;
                $bar->advance();
            }
            if ($sleep > 0) usleep($sleep * 1000);
        }

        $bar->finish();
        $this->line('');
        $this->info("Processed: {$processed}");
        return 0;
    }
}
