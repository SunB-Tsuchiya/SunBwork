<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiHistoryConsolidator;

class ConsolidateAiHistory extends Command
{
    protected $signature = 'ai:consolidate-history {--dry-run} {--minutes=30}';
    protected $description = 'Consolidate fragmented AI conversation records into single conversations (dry-run supported)';

    public function handle(AiHistoryConsolidator $consolidator)
    {
        $dry = $this->option('dry-run');
        $minutes = (int) $this->option('minutes');
        $this->info('Running AI history consolidation' . ($dry ? ' (dry-run)' : ''));
        $result = $consolidator->run(['dry' => $dry, 'threshold_minutes' => $minutes]);
        $this->info("Found groups: {$result['groups_found']}");
        $this->info("Merged conversations: {$result['merged_count']}");
        $this->info('Done.');
        return 0;
    }
}
