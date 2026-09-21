<?php

namespace App\Console\Commands\MGinbon;

use App\Services\MGinbon\MGinbonImportPreviewService;
use Illuminate\Console\Command;
use Throwable;

class MGinbonImportPreview extends Command
{
    protected $signature = 'mginbon:import-preview
        {path? : FileMakerから出力したヘッダー付きMergeファイル}
        {--json : 集計結果をJSONで表示する}';

    protected $description = 'MGinbon移行元Mergeを読み取り、DBへ書き込まず構造と件数を検査する';

    public function handle(MGinbonImportPreviewService $service): int
    {
        $path = $this->argument('path')
            ?: base_path('z_NDBSystem/N_DBSystem_DDR/NDBSystem_2026_全件全項目.mer');

        try {
            $summary = $service->preview($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(['検査項目', '値'], collect($summary)->map(
                fn ($value, $key) => [$key, is_scalar($value) ? (string) $value : json_encode($value)]
            )->values()->all());
        }

        $this->info('ドライラン完了: データベースへの書込みはありません。');

        return self::SUCCESS;
    }
}
