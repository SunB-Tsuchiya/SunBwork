<?php

namespace App\Console\Commands\MGinbon;

use App\Services\MGinbon\MGinbonNormalizationPreviewService;
use Illuminate\Console\Command;
use Throwable;

class MGinbonNormalizePreview extends Command
{
    protected $signature = 'mginbon:normalize-preview {--batch= : 取込バッチID} {--force : 確認を省略する}';

    protected $description = 'ステージング原文から正規化候補JSONを生成する（正式データは作成しない）';

    public function handle(MGinbonNormalizationPreviewService $service): int
    {
        if (! $this->option('force') && ! $this->confirm('ステージング行へ正規化候補を生成します。続けますか？')) {
            return self::SUCCESS;
        }

        try {
            $summary = $service->generate($this->option('batch') ? (int) $this->option('batch') : null);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['行数', '自動候補', '要確認', '警告数'], [[
            $summary['rows'], $summary['candidate'], $summary['review_required'], $summary['warning_count'],
        ]]);
        $this->info('正規化候補を生成しました。正式データへの確定はまだ行っていません。');

        return self::SUCCESS;
    }
}
