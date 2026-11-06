<?php

namespace App\Console\Commands;

use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Services\Portfolio\PortfolioStorage;
use Illuminate\Console\Command;

/**
 * ポートフォリオで、アップ途中・変換途中・変換失敗のまま残った作品を掃除する。
 * 画面を閉じた・通信が切れた・変換に失敗した、などで残るもの（status が ready 以外）を、
 * 一定時間（既定 24 時間）経過後に、実体ファイル（アップ途中の一時ファイルを含む）ごと削除する。
 * 本人が一覧から先に削除していれば対象外。完了（ready）の作品には触れない。
 */
class PrunePortfolioStaleUploads extends Command
{
    protected $signature = 'portfolio:prune-stale {--hours=24 : この時間より前に更新された未完了の作品を削除する} {--dry-run : 削除せず対象だけ表示する}';

    protected $description = 'ポートフォリオの未完了（アップ途中・変換中・失敗）の作品を、実体ファイルごと削除する';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $stale = PortfolioItem::where('status', '!=', 'ready')
            ->where('updated_at', '<', $cutoff)
            ->with('pages')
            ->get();

        foreach ($stale as $item) {
            $this->line(sprintf('#%d user=%d type=%s status=%s updated=%s "%s"', $item->id, $item->user_id, $item->type, $item->status, $item->updated_at, $item->title));
            if ($this->option('dry-run')) {
                continue;
            }
            PortfolioStorage::deleteItemFiles($item);
            // 表紙に指定されていたら外す（通常は ready のみ指定できるが念のため）
            PortfolioCollection::where('user_id', $item->user_id)->where('cover_item_id', $item->id)->update(['cover_item_id' => null]);
            $item->delete();
        }

        $this->info(($this->option('dry-run') ? '[dry-run] 対象 ' : '削除 ') . $stale->count() . ' 件');

        return self::SUCCESS;
    }
}
