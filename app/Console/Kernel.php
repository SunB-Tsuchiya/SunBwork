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
        \App\Console\Commands\AutoCompleteMyJobs::class,
    ];

    // Laravel 11 では Kernel::schedule() は自動呼出しされないため routes/console.php に移行済み
    // protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
    // {
    //     $schedule->command('ai:dispatch-summaries')->everyFiveMinutes();
    //     $schedule->command('auto-complete:my-jobs')->dailyAt('00:05');
    // }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
