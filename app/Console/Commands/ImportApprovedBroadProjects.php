<?php

namespace App\Console\Commands;

use App\Services\ApprovedBroadProjectImport;
use Illuminate\Console\Command;

class ImportApprovedBroadProjects extends Command
{
    protected $signature = 'broad-projects:import-approved {--company=} {--department=} {--dry-run} {--force}';
    protected $description = '承認済みの分類を対象会社/部署へ登録（Web編集を保持して再実行可能）';

    public function handle(ApprovedBroadProjectImport $import): int
    {
        $company = filter_var($this->option('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $department = filter_var($this->option('department'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $company || ! $department || (app()->environment('production') && ! $this->option('force'))) {
            $this->error('--company と --department が必要です。本番は --force も指定してください。');
            return self::FAILURE;
        }
        $result = $import->run($company, $department, (bool) $this->option('dry-run'), 'mysql');
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
