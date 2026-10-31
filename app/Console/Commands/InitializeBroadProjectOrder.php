<?php

namespace App\Console\Commands;

use App\Services\ApprovedBroadProjectOrder;
use Illuminate\Console\Command;

class InitializeBroadProjectOrder extends Command
{
    protected $signature = 'broad-projects:initialize-order {--company=} {--department=} {--dry-run} {--force}';
    protected $description = '未設定の分類の並びを承認済みExcel順に初期化（既存の並べ替えは保持）';

    public function handle(ApprovedBroadProjectOrder $order): int
    {
        $company = filter_var($this->option('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $department = filter_var($this->option('department'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $company || ! $department || (app()->environment('production') && ! $this->option('force'))) {
            $this->error('--company と --department が必要です。本番は --force も指定してください。');
            return self::FAILURE;
        }
        $this->line(json_encode($order->run($company, $department, (bool) $this->option('dry-run'), 'mysql'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
