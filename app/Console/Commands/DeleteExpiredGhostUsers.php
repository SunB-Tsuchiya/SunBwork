<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteExpiredGhostUsers extends Command
{
    protected $signature = 'ghost:cleanup';
    protected $description = '有効期限切れのゴーストユーザーを削除する';

    public function handle(): int
    {
        $expired = User::withGhosts()
            ->where('is_ghost', true)
            ->where('ghost_expires_at', '<', now())
            ->get(['id']);

        if ($expired->isEmpty()) {
            $this->info('削除対象のゴーストユーザーはありません。');
            return self::SUCCESS;
        }

        $ids = $expired->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            DB::table('project_job_assignments')->whereIn('user_id', $ids)->delete();
            DB::table('events')->whereIn('user_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
        });

        $this->info(count($ids) . ' 件のゴーストユーザーを削除しました。');

        return self::SUCCESS;
    }
}
