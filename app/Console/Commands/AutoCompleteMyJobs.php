<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * マイジョブ（自己割当ジョブ）自動完了バッチ
 *
 * 対象: project_job_assignments で sender_id = user_id（自己割当）かつ completed = false
 * 条件: scheduled_at が今日の 00:00 より前（優先）
 *       または desired_end_date が昨日以前（次候補）
 *       どちらの日付もない場合は対象外
 *
 * 実行: php artisan auto-complete:my-jobs
 * スケジュール: 毎日 00:05 に自動実行（Kernel.php に登録済み）
 */
class AutoCompleteMyJobs extends Command
{
    protected $signature   = 'auto-complete:my-jobs {--dry-run : 実際には更新せず対象件数のみ表示}';
    protected $description = 'マイジョブのうち日付が過ぎたものを自動で完了にする';

    public function handle(): int
    {
        $today     = Carbon::today();   // 今日の 00:00:00（JST）
        $isDryRun  = $this->option('dry-run');

        // ── 対象クエリ ──────────────────────────────────────────────────────
        // sender_id = user_id → 自己割当（マイジョブ）
        // completed = false（未完了のみ）
        // scheduled_at < today OR desired_end_date < today（どちらかが超過）
        $query = DB::table('project_job_assignments')
            ->whereColumn('sender_id', 'user_id')
            ->where('completed', false)
            ->where(function ($q) use ($today) {
                $q->where(function ($q2) use ($today) {
                    // scheduled_at が設定済み かつ 今日の 00:00 より前
                    $q2->whereNotNull('scheduled_at')
                       ->where('scheduled_at', '<', $today);
                })->orWhere(function ($q2) use ($today) {
                    // scheduled_at がない かつ desired_end_date が昨日以前
                    $q2->whereNull('scheduled_at')
                       ->whereNotNull('desired_end_date')
                       ->where('desired_end_date', '<', $today->toDateString());
                });
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('自動完了対象のマイジョブはありません。');
            return self::SUCCESS;
        }

        $this->info("自動完了対象: {$count} 件");

        if ($isDryRun) {
            $this->warn('--dry-run モード: 更新はスキップしました。');
            return self::SUCCESS;
        }

        // ── 更新 ────────────────────────────────────────────────────────────
        // project_job_assignments には completed_at カラムが存在しないため completed のみ更新
        $now     = Carbon::now();
        $updated = (clone $query)->update([
            'completed'  => true,
            'updated_at' => $now,
        ]);

        $this->info("完了に更新しました: {$updated} 件");

        return self::SUCCESS;
    }
}
