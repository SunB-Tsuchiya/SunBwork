<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * サーバーローカルにインストールされた Tesseract OCR を使う実装。
 * OcrSpaceService を継承し、analyze() のみ上書きする。
 * パース処理・クライアント検索は親クラスの protected メソッドをそのまま利用。
 *
 * 必要な環境設定（さくらサーバー）:
 *   TESSERACT_BINARY=/home/silverlamb759/local/bin/tesseract
 *   TESSERACT_LIB_PATH=/home/silverlamb759/local/lib
 *   TESSDATA_PREFIX=/home/silverlamb759/local/share/tessdata
 *
 * ─── クロップ領域の調整 ────────────────────────────────────────────────────
 * 伝票レイアウト（y 方向は画像全体に対する割合）:
 *   row1 (y≈8.8〜12.8%): [受注番号 label][受注番号値][得意先 label][コード][クライアント名]
 *   row2 (y≈12.7〜17.0%): [品名 label][品名テキスト]
 *
 * 各領域の x 座標は実際の伝票に合わせて REGION_* 定数を調整してください。
 * ─────────────────────────────────────────────────────────────────────────
 */
class LocalTesseractService extends OcrSpaceService
{
    // 受注番号値エリア（黄色背景ボックス）― 数字専用クロップ
    // x: 左から約 8〜28%、y: row1
    private const REGION_JOBCODE  = [0.080, 0.088, 0.280, 0.128];

    // row1 + row2 全体（クライアント名・品名テキスト取得用）
    // Tesseract が列を混在させるため合算クロップし、パース処理で分離する
    private const REGION_COMBINED = [0.003, 0.088, 0.660, 0.170];

    public function analyze(string $storagePath): array
    {
        $absPath = Storage::disk('public')->path($storagePath);

        $realPath = realpath($absPath);
        $baseDir  = realpath(Storage::disk('public')->path(''));
        if (!$realPath || !str_starts_with($realPath, $baseDir)) {
            Log::warning('LocalTesseractService: invalid path rejected', ['path' => $storagePath]);
            return $this->emptyResult();
        }

        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            Log::error('LocalTesseractService: Imagick not available');
            return $this->emptyResult();
        }

        $binary = config('services.tesseract.binary', '/usr/bin/tesseract');
        if (!file_exists($binary)) {
            Log::error('LocalTesseractService: tesseract binary not found', ['binary' => $binary]);
            return $this->emptyResult();
        }

        try {
            $imagick = new \Imagick($realPath);
            $w = $imagick->getImageWidth();
            $h = $imagick->getImageHeight();

            // 受注番号: 数字専用クロップ（確実に取得）
            $jobcodeRaw  = $this->cropAndOcr($imagick, $w, $h, self::REGION_JOBCODE,  $binary, 7);
            // クライアント名 + 品名: 合算クロップ（分離クロップでは日本語誤認識が増えるため）
            $combinedRaw = $this->cropAndOcr($imagick, $w, $h, self::REGION_COMBINED, $binary, 6);

            $imagick->clear();
            $imagick->destroy();

            Log::info('LocalTesseractService: raw OCR', [
                'jobcode_raw'  => $jobcodeRaw,
                'combined_raw' => $combinedRaw,
            ]);

            // 受注番号: 数字のみ抽出
            $jobcode = preg_replace('/[^0-9]/', '', $jobcodeRaw);
            if (!preg_match('/^\d{5,12}$/', $jobcode)) {
                $jobcode = $this->parseJobcode($combinedRaw);
            }

            // クライアント名: jobcode行から数字を除いた残りを取得
            $clientName = $this->parseClientNameTesseract($combinedRaw);

            // 品名: 「品名」ラベル以降を抽出 → クリーニング
            $title = $this->cleanTitle($this->parseTitleTesseract($combinedRaw));

            $matchedClients = $this->searchClients($clientName);

            Log::info('LocalTesseractService: OCR completed', [
                'jobcode'     => $jobcode,
                'client_name' => $clientName,
                'title'       => mb_substr($title, 0, 50),
            ]);

            return [
                'jobcode'         => $jobcode,
                'client_name'     => $clientName,
                'title'           => $title,
                'matched_clients' => $matchedClients,
            ];

        } catch (\Throwable $e) {
            Log::error('LocalTesseractService: exception', ['message' => $e->getMessage()]);
            return $this->emptyResult();
        }
    }

    /**
     * Tesseract 向けクライアント名抽出。
     *
     * Tesseract は受注番号行を "4505963 クライアント名" のように1行で出力する。
     * jobcode（7桁以上の数字）を見つけ、その行から数字を除いた残りをクライアント名とする。
     */
    private function parseClientNameTesseract(string $text): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
            fn($l) => $l !== ''
        ));

        foreach ($lines as $line) {
            if (preg_match('/\d{7,12}/', $line)) {
                // 数字をすべて除去
                $rest = trim(preg_replace('/\d+/', '', $line));
                // 先頭・末尾の記号ノイズを除去
                $rest = trim(preg_replace('/^[\s　|lｌ｜\[\]()\-]+/u', '', $rest));
                $rest = trim(preg_replace('/[\s　|lｌ｜\[\]()\-]+$/u', '', $rest));
                if (mb_strlen($rest) >= 2) {
                    return $rest;
                }
            }
        }

        // フォールバック: 親クラスの 4〜6桁コード行ロジック
        return parent::parseClientName($text);
    }

    /**
     * Tesseract 向け品名テキスト抽出。
     * 「品名」「品目」ラベルの後に続くテキストをすべて返す（クリーニングは cleanTitle で行う）。
     */
    private function parseTitleTesseract(string $text): string
    {
        if (preg_match('/品[名目][\s　\r\n]+([\s\S]+)/u', $text, $m)) {
            return trim($m[1]);
        }
        return parent::parseTitle($text);
    }

    /**
     * 画像の指定領域をクロップして Tesseract でOCRし、テキストを返す。
     *
     * @param  \Imagick  $imagick  元画像（破壊しない）
     * @param  int       $w        元画像の幅
     * @param  int       $h        元画像の高さ
     * @param  float[]   $region   [x1%, y1%, x2%, y2%] (0.0〜1.0)
     * @param  string    $binary   tesseract バイナリパス
     * @param  int       $psm      ページセグメンテーションモード（7=1行, 6=ブロック）
     * @return string              OCR結果テキスト（失敗時は空文字）
     */
    private function cropAndOcr(
        \Imagick $imagick,
        int $w,
        int $h,
        array $region,
        string $binary,
        int $psm = 6
    ): string {
        [$x1p, $y1p, $x2p, $y2p] = $region;
        $x  = (int)($w * $x1p);
        $y  = (int)($h * $y1p);
        $cw = max(1, (int)($w * ($x2p - $x1p)));
        $ch = max(1, (int)($h * ($y2p - $y1p)));

        $crop = clone $imagick;
        $crop->cropImage($cw, $ch, $x, $y);
        $crop->setImagePage($cw, $ch, 0, 0);

        // 高さが不足する場合は拡大（最低 80px）
        if ($ch < 80) {
            $scale = (int)ceil(80 / $ch);
            $crop->resizeImage($cw * $scale, $ch * $scale, \Imagick::FILTER_LANCZOS, 1);
        }

        $crop->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
        $crop->normalizeImage();
        $crop->setImageFormat('png');

        $tmpPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
        $crop->writeImage($tmpPath);
        $crop->clear();
        $crop->destroy();

        $result = $this->runTesseract($binary, $tmpPath, $psm);
        @unlink($tmpPath);

        return $result ?? '';
    }

    /**
     * 品名テキストのクリーニング:
     *   1. 先頭の「品名」「品目」ラベルを除去
     *   2. 日本語文字（CJK・かな）間の Tesseract 字間スペースを除去
     *      例: "日 本 医科 大 学" → "日本医科大学"
     *   3. 末尾の記号ノイズ（| T 等）を除去
     */
    private function cleanTitle(string $text): string
    {
        $text = trim($text);

        // 先頭の記号ノイズ（| l ｜ 等）を除去
        $text = preg_replace('/^[\s　|lｌ｜]+/u', '', $text);

        // 「品名」「品目」ラベルを先頭から除去
        $text = preg_replace('/^[\s　]*品[名目][\s　]*/u', '', $text);

        // 日本語文字間のスペースを除去（英数字間のスペースは保持）
        $text = preg_replace(
            '/(?<=[\x{3040}-\x{9fff}\x{f900}-\x{faff}])\s+(?=[\x{3040}-\x{9fff}\x{f900}-\x{faff}])/u',
            '',
            $text
        );

        // 末尾の記号ノイズを除去
        $text = preg_replace('/[\s　|Tl｜]+$/u', '', $text);

        return trim($text);
    }

    /**
     * tesseract バイナリを実行してテキストを返す。
     * 失敗時は null を返す。
     *
     * @param  int  $psm  ページセグメンテーションモード（7=1行, 6=ブロック）
     */
    private function runTesseract(string $binary, string $imagePath, int $psm = 6): ?string
    {
        $libPath  = config('services.tesseract.lib_path', '');
        $tessdata = config('services.tesseract.tessdata_prefix', '');

        $env = [];
        if ($libPath)  $env['LD_LIBRARY_PATH'] = $libPath;
        if ($tessdata) $env['TESSDATA_PREFIX']  = $tessdata;

        $cmd = sprintf(
            '%s %s stdout -l jpn+eng --psm %d 2>/dev/null',
            escapeshellarg($binary),
            escapeshellarg($imagePath),
            $psm
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, null, $env ?: null);
        if (!is_resource($proc)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        if ($exit !== 0 && empty(trim($stdout))) {
            return null;
        }

        return $stdout;
    }

    private function emptyResult(): array
    {
        return [
            'jobcode'         => '',
            'client_name'     => '',
            'title'           => '',
            'matched_clients' => [],
        ];
    }
}
