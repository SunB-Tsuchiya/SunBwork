<?php

namespace App\Console\Commands;

use App\Services\WorkJobCompletionSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncWorkJobCompletions extends Command
{
    protected $signature = 'work-jobs:sync-completions';
    protected $description = 'Retry pending trial MyJob MGinbon completion syncs';

    public function handle(WorkJobCompletionSync $sync): int
    {
        if (! Schema::hasTable('work_job_completion_syncs')) return self::SUCCESS;
        $failed = 0;
        foreach (DB::table('work_job_completion_syncs')->whereNull('synced_at')->orderBy('updated_at')->orderBy('id')->limit(100)->pluck('id') as $id) {
            if (! $sync->run((int) $id)) $failed++;
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
