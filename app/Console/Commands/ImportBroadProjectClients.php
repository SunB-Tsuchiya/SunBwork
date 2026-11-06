<?php

namespace App\Console\Commands;

use App\Services\BroadProjectClientImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportBroadProjectClients extends Command
{
    protected $signature = 'broad-projects:import-clients {file} {--company=} {--department=} {--database-name=} {--dry-run} {--force}';
    protected $description = '確認済みID表から分類の関連クライアントを追加（既存関連は保持）';

    public function handle(BroadProjectClientImport $import): int
    {
        $company = filter_var($this->option('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $department = filter_var($this->option('department'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $company || ! $department || ! $this->option('database-name')
            || $this->option('database-name') !== DB::connection('mysql')->getDatabaseName()
            || (! $this->option('dry-run') && ! $this->option('force'))) {
            $this->error('会社・部署・対象mysql DB名の一致が必要です。書込には--forceが必要です。');
            return self::FAILURE;
        }
        $file = $this->argument('file');
        if (! is_file($file) || filesize($file) > 1024 * 1024) {
            $this->error('1MB以下の確認済みJSONファイルを指定してください。'); return self::FAILURE;
        }
        $manifest = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $this->line(json_encode($import->run($company, $department, $manifest, (bool) $this->option('dry-run')), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
