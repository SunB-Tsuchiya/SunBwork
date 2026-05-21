<?php

namespace App\Http\Controllers\Concerns;

trait NormalizesCsvEncoding
{
    /**
     * Shift-JIS / CRLF / BOM を正規化して UTF-8 LF に変換する。
     * $file->store() で保存済みのファイルをその場で上書きする。
     *
     * @param  string  $storagePath  Storage::path() に渡すパス（local ディスク）
     */
    protected function normalizeCsvStoredFile(string $storagePath): void
    {
        $fullPath = \Storage::disk('local')->path($storagePath);
        $content  = file_get_contents($fullPath);
        file_put_contents($fullPath, $this->normalizeCsvContent($content));
    }

    /**
     * UploadedFile の生バイト列を正規化して一時ファイルパスを返す。
     * fopen($path, 'r') で直接読める状態にする。
     * 使用後は @unlink($path) すること。
     */
    protected function normalizeCsvToTemp(\Illuminate\Http\UploadedFile $file): string
    {
        $content = file_get_contents($file->getRealPath());
        $tmpPath = tempnam(sys_get_temp_dir(), 'csv_norm_');
        file_put_contents($tmpPath, $this->normalizeCsvContent($content));
        return $tmpPath;
    }

    /**
     * CSV バイト列から BOM 除去・文字コード変換（Shift-JIS 対応）・CRLF 正規化を行う。
     */
    protected function normalizeCsvContent(string $raw): string
    {
        // UTF-8 BOM 除去
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        // 文字コード検出・変換（Shift-JIS 等 → UTF-8）
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'SJIS-win,UTF-8,ASCII');
        }

        // CRLF / CR → LF 正規化
        return str_replace(["\r\n", "\r"], "\n", $raw);
    }
}
