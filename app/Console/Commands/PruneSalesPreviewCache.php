<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * 売上分析Excel取込のプレビューキャッシュ（期限切れファイル）を削除し、ディスク使用量をログに記録する。
 * 2026-09-04追加（Codexレビュー2回目 8.1 Medium-3対応: ファイルキャッシュの期限切れ残留・
 * ディスク使用量の監視が未整理だった点への対応）。
 *
 * `config('sales_analysis.import_preview_cache_store')`（既定 sales_preview）専用の独立ディレクトリを
 * 対象にする。TTL（`preview_cache_ttl_minutes`）の2倍より古いファイルだけを削除し、
 * 確定処理中のプレビューを誤って消さないよう猶予を持たせる。
 */
class PruneSalesPreviewCache extends Command
{
    protected $signature = 'sales:prune-preview-cache';

    protected $description = '売上分析Excel取込のプレビューキャッシュ（期限切れファイル）を削除し、ディスク使用量をログに記録する';

    public function handle(): int
    {
        $storeName = config('sales_analysis.import_preview_cache_store', 'sales_preview');
        $storeConfig = config("cache.stores.{$storeName}");

        if (! is_array($storeConfig) || ($storeConfig['driver'] ?? null) !== 'file') {
            // テスト環境（arrayストア等）では削除対象のディレクトリが存在しないため何もしない
            $this->info('プレビューキャッシュストアがfileドライバではないため、何もしませんでした。');

            return self::SUCCESS;
        }

        $path = $storeConfig['path'];

        if (! is_dir($path)) {
            $this->info('プレビューキャッシュディレクトリがまだ存在しません。');

            return self::SUCCESS;
        }

        $ttlMinutes = (int) config('sales_analysis.preview_cache_ttl_minutes', 30);
        // TTLちょうどで削除すると確定処理の最終盤と衝突し得るため、TTLの2倍の猶予を持たせる
        $cutoff = time() - ($ttlMinutes * 60 * 2);

        $deletedCount = 0;
        $deletedBytes = 0;

        foreach (File::allFiles($path) as $file) {
            if ($file->getMTime() < $cutoff) {
                $deletedBytes += $file->getSize();
                @unlink($file->getPathname());
                $deletedCount++;
            }
        }

        $remainingBytes = 0;
        foreach (File::allFiles($path) as $file) {
            $remainingBytes += $file->getSize();
        }

        $message = sprintf(
            '[sales:prune-preview-cache] 削除%d件（%s）・残存%s',
            $deletedCount,
            $this->formatBytes($deletedBytes),
            $this->formatBytes($remainingBytes)
        );

        $this->info($message);
        Log::info($message);

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / 1024 / 1024, 2) . 'MB'
            : round($bytes / 1024, 1) . 'KB';
    }
}
