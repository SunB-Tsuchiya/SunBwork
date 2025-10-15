<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ConsolidateAiHistory::class,
        \App\Console\Commands\MigrateJobRequestsToMessages::class,
        \App\Console\Commands\TestTeamOwners::class,
        \App\Console\Commands\DispatchSummaries::class,
        \App\Console\Commands\MigrateAttachmentsToAttachmentables::class,
        \App\Console\Commands\TestTeamDeletePivot::class,
    ];

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
    {
        // Run the summary dispatcher every 5 minutes to detect conversations that need summarization
        $schedule->command('ai:dispatch-summaries')->everyFiveMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
