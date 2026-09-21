<?php

namespace App\Console\Commands\MGinbon;

use App\Services\MGinbon\MGinbonStagingImportService;
use Illuminate\Console\Command;
use Throwable;

class MGinbonStageImport extends Command
{
    protected $signature = 'mginbon:stage-import
        {path? : FileMakerから出力したヘッダー付きMergeファイル}
        {--year=2026 : 対象年度}
        {--force : 確認を省略する}';

    protected $description = 'MGinbon専用DBへFileMaker原文を検証用ステージング取込する';

    public function handle(MGinbonStagingImportService $service): int
    {
        $path = $this->argument('path')
            ?: base_path('z_NDBSystem/N_DBSystem_DDR/NDBSystem_2026_全件全項目.mer');
        $year = (int) $this->option('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('年度は2000～2100で指定してください。');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            "専用DBへ{$year}年度の検証用原文データを追加します。続けますか？"
        )) {
            $this->line('キャンセルしました。');

            return self::SUCCESS;
        }

        try {
            $summary = $service->import($path, $year);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['項目', '件数'], [
            ['取込バッチ', $summary['batch_id']],
            ['ステージング行', $summary['staged_rows']],
            ['要確認行', $summary['review_required']],
        ]);
        $this->info('検証用ステージング取込が完了しました。正規化データはまだ作成していません。');

        return self::SUCCESS;
    }
}
