<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;

/**
 * マイジョブ（自己割当ジョブ）自動完了バッチ
 *
 * 対象: project_job_assignments で sender_id = user_id（自己割当）かつ completed = false
 * 条件: scheduled_at が今日の 00:00 より前（優先）
 *       または desired_end_date が昨日以前（次候補）
 *       どちらの日付もない場合は対象外
 *
 * 実行: php artisan auto-complete:my-jobs
 * 新形式: INFO本人入力の work_date が昨日以前かつ completed_at=NULL（元イベント派生は除外）
 * スケジュール: 毎日 00:05 JST（routes/console.php）
 */
class AutoCompleteMyJobs extends Command
{
    protected $signature   = 'auto-complete:my-jobs {--dry-run : 実際には更新せず対象件数のみ表示}';
    protected $description = 'マイジョブのうち日付が過ぎたものを自動で完了にする';

    public function handle(): int
    {
        $today     = Carbon::today('Asia/Tokyo');   // 今日の 00:00:00（JST）
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
        $entries = $this->newEntries($today->toDateString());
        $entryCount = $entries->count();
        $this->info("旧形式の対象: {$count} 件 / 新形式の対象: {$entryCount} 件");

        if ($count + $entryCount === 0) {
            $this->info('自動完了対象のマイジョブはありません。');
            return self::SUCCESS;
        }

        $this->info("自動完了対象: ".($count + $entryCount)." 件");

        if ($isDryRun) {
            $this->warn('--dry-run モード: 更新はスキップしました。');
            return self::SUCCESS;
        }

        // ── 更新 ────────────────────────────────────────────────────────────
        $now     = Carbon::now('Asia/Tokyo');

        // 対象IDを取得してから更新（イベント更新に使うため）
        $targetIds = (clone $query)->pluck('id')->toArray();

        // status_id=3（完了）も同時に更新する（completed フラグだけ立てると画面表示が不一致になるため）
        $updated = (clone $query)->update([
            'completed'  => true,
            'status_id'  => 3,
            'updated_at' => $now,
        ]);

        $this->info("完了に更新しました: {$updated} 件");

        // ── 紐付くカレンダーイベントのタイトルに【完了】を付加 ──────────────
        // CalendarController / Calendar.vue はタイトルの先頭が「【完了】」かどうかで金色表示を判定するため
        if (!empty($targetIds)) {
            $prefix  = '【完了】';
            $events  = \App\Models\Event::whereIn('project_job_assignment_id', $targetIds)
                ->where(function ($q) use ($prefix) {
                    $q->whereNull('title')
                      ->orWhere('title', 'not like', $prefix . '%');
                })
                ->get(['id', 'title']);

            $eventCount = 0;
            foreach ($events as $ev) {
                $ev->title = $prefix . ($ev->title ?? '');
                $ev->save();
                $eventCount++;
            }
            $this->info("カレンダーイベントに【完了】を付加: {$eventCount} 件");
        }

        // Do not invoke interactive completion: it follows ancestors and sends notifications.
        // Recheck every eligibility predicate at UPDATE time (e.g. a rescheduled future row).
        $entryUpdated = (clone $entries)->update(['completed_at' => $now, 'updated_at' => $now]);
        $this->info("新形式を完了に更新しました: {$entryUpdated} 件");

        return self::SUCCESS;
    }

    private function newEntries(string $today): Builder
    {
        return DB::table('personal_work_entries')
            ->whereNull('personal_work_entries.completed_at')
            ->whereNull('personal_work_entries.source_event_id')
            ->where('personal_work_entries.work_date', '<', $today)
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('companies')
                ->whereColumn('companies.id', 'personal_work_entries.company_id')->where('companies.company_type', 'sunbrain'))
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('departments')
                ->whereColumn('departments.id', 'personal_work_entries.department_id')
                ->whereColumn('departments.company_id', 'personal_work_entries.company_id')->where('departments.code', 'INFO'))
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('users')
                ->whereColumn('users.id', 'personal_work_entries.user_id')
                ->whereColumn('users.company_id', 'personal_work_entries.company_id')
                ->whereColumn('users.department_id', 'personal_work_entries.department_id'));
    }
}
