<?php

namespace App\Console\Commands;

use App\Models\ClerkScheduleRule;
use App\Services\ClerkScheduleGenerator;
use Illuminate\Console\Command;

class GenerateClerkSchedules extends Command
{
    protected $signature = 'clerk:generate-schedules';

    protected $description = 'Clerkの有効な予定日設定から今後の予定を補充する';

    public function handle(ClerkScheduleGenerator $generator): int
    {
        ClerkScheduleRule::where('is_active', true)->eachById(fn ($rule) => $generator->generate($rule->id));
        $this->info('予定日設定の補充が完了しました。');

        return self::SUCCESS;
    }
}
