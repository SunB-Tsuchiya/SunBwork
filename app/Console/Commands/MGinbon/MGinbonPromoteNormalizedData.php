<?php

namespace App\Console\Commands\MGinbon;

use App\Services\MGinbon\MGinbonNormalizedDataPromotionService;
use Illuminate\Console\Command;
use Throwable;

class MGinbonPromoteNormalizedData extends Command
{
    protected $signature = 'mginbon:promote-normalized {--batch= : 取込バッチID} {--force : 確認を省略する}';

    protected $description = '正規化候補を専用DBの正式テーブルへ下書きとして展開する';

    public function handle(MGinbonNormalizedDataPromotionService $service): int
    {
        if (! $this->option('force') && ! $this->confirm('正規化候補を正式テーブルへ下書き展開します。続けますか？')) {
            return self::SUCCESS;
        }

        try {
            $summary = $service->promote($this->option('batch') ? (int) $this->option('batch') : null);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['対象', '展開', '既存', '制作単位', '媒体', '教科', '工程', '旧担当候補', '日付', '点数'],
            [[...array_values($summary)]]
        );
        $this->info('下書き展開が完了しました。要確認行と旧担当候補は未確定のまま保持しています。');

        return self::SUCCESS;
    }
}
