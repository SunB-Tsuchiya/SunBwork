<?php

namespace App\Console\Commands;

use App\Models\ProjectJobAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateMyJobRegistrations extends Command
{
    protected $signature = 'myjob:cleanup-duplicates
                            {--execute : 実際に削除・補完を実行（省略時はDryRunのみ）}';

    protected $description = '重複したマイジョブ登録を削除し、is_registered を補完する';

    public function handle(): int
    {
        $execute = $this->option('execute');

        if (!$execute) {
            $this->info('=== DRY RUN モード（実行するには --execute を付けてください） ===');
        } else {
            $this->warn('=== 実行モード：データを変更します ===');
        }

        // ① 重複（複数の self-assigned が同じ supersedes_assignment_id を指す）を検出
        $duplicateSources = DB::table('project_job_assignments')
            ->select('supersedes_assignment_id')
            ->whereNotNull('supersedes_assignment_id')
            ->whereColumn('sender_id', 'user_id')
            ->groupBy('supersedes_assignment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('supersedes_assignment_id');

        $this->info("重複登録が見つかったコーディネーター割当: {$duplicateSources->count()} 件");

        $totalDeleted = 0;
        $totalEventsRelinked = 0;

        foreach ($duplicateSources as $sourceId) {
            // 最初に登録したもの（最小ID）を保持し、残りを削除
            $records = ProjectJobAssignment::where('supersedes_assignment_id', $sourceId)
                ->whereColumn('sender_id', 'user_id')
                ->orderBy('id', 'asc')
                ->get(['id', 'user_id', 'created_at']);

            $keepRecord = $records->first();
            $toDelete   = $records->skip(1);

            $this->line("  coordinator_id={$sourceId}: id={$keepRecord->id} を保持 / 削除対象: " . $toDelete->pluck('id')->join(', '));

            foreach ($toDelete as $rec) {
                $eventCount = DB::table('events')
                    ->where('project_job_assignment_id', $rec->id)
                    ->count();

                if ($eventCount > 0) {
                    $this->line("    → id={$rec->id} に events {$eventCount}件: project_job_assignment_id を {$keepRecord->id} に付け替え");
                    if ($execute) {
                        DB::table('events')
                            ->where('project_job_assignment_id', $rec->id)
                            ->update(['project_job_assignment_id' => $keepRecord->id]);
                        $totalEventsRelinked += $eventCount;
                    }
                }

                $this->line("    → id={$rec->id} を削除");
                if ($execute) {
                    ProjectJobAssignment::where('id', $rec->id)->delete();
                }
                $totalDeleted++;
            }
        }

        $this->info("削除対象: {$totalDeleted} 件 / events 付け替え: {$totalEventsRelinked} 件");

        // ② is_registered の補完（supersedes_assignment_id で登録済みなのに is_registered = false のもの）
        $supersededIds = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
            ->whereNotNull('supersedes_assignment_id')
            ->pluck('supersedes_assignment_id')
            ->unique()
            ->values();

        $toBackfill = ProjectJobAssignment::whereIn('id', $supersededIds)
            ->where(function ($q) {
                $q->whereNull('is_registered')->orWhere('is_registered', false);
            })
            ->count();

        $this->info("is_registered 補完対象: {$toBackfill} 件");

        if ($execute && $toBackfill > 0) {
            ProjectJobAssignment::whereIn('id', $supersededIds)
                ->where(function ($q) {
                    $q->whereNull('is_registered')->orWhere('is_registered', false);
                })
                ->update(['is_registered' => true]);
            $this->info('is_registered 補完完了');
        }

        if (!$execute) {
            $this->info('');
            $this->info('実行するには: php artisan myjob:cleanup-duplicates --execute');
        } else {
            $this->info('クリーンアップ完了');
        }

        return Command::SUCCESS;
    }
}
