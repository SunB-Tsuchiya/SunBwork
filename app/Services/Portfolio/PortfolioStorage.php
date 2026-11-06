<?php

namespace App\Services\Portfolio;

use App\Models\Portfolio\PortfolioItem;
use Illuminate\Support\Facades\Storage;

/**
 * ポートフォリオのファイル保存先と容量管理。
 * 保存先は公開ディレクトリ外（local ディスク）。配信は認証付きコントローラー経由のみ。
 */
class PortfolioStorage
{
    /** 個人あたりの総容量上限（10GB。PLAN §8） */
    public const QUOTA_BYTES = 10 * 1024 * 1024 * 1024;

    /** 実際に使う上限（テストで config('filesystems.portfolio_quota') により差し替え可能） */
    public static function quota(): int
    {
        return (int) config('filesystems.portfolio_quota', self::QUOTA_BYTES);
    }

    public static function disk()
    {
        // テストでは別ディスクに差し替える（config('filesystems.portfolio_disk')）。本番は local（公開ディレクトリ外）
        return Storage::disk(config('filesystems.portfolio_disk', 'local'));
    }

    public static function dir(int $userId): string
    {
        return "portfolio/{$userId}";
    }

    public static function usedBytes(int $userId): int
    {
        return (int) PortfolioItem::where('user_id', $userId)->sum('size_bytes');
    }

    public static function hasRoom(int $userId, int $addBytes): bool
    {
        return self::usedBytes($userId) + $addBytes <= self::quota();
    }

    /** 分割アップロード中の一時ファイル（アップ完了で本体へ移動する） */
    public static function partPath(PortfolioItem $item): string
    {
        return self::dir($item->user_id) . "/tmp/{$item->id}.part";
    }

    /** 作品に紐づくファイル（本体・サムネイル・PDF ページ画像・アップ途中の一時ファイル）をすべて削除する */
    public static function deleteItemFiles(PortfolioItem $item): void
    {
        $paths = array_filter([$item->file_path, $item->thumb_path, $item->poster_path, self::partPath($item)]);
        foreach ($item->pages as $page) {
            $paths[] = $page->path;
        }
        if ($paths) {
            self::disk()->delete($paths);
        }
    }
}
