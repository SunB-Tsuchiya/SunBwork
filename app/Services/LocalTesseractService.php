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
 */
class LocalTesseractService extends OcrSpaceService
{
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

            // OcrSpaceService と同じクロップ領域・前処理
            [$x1p, $y1p, $x2p, $y2p] = [0.003, 0.088, 0.660, 0.170];
            $x  = (int)($w * $x1p);
            $y  = (int)($h * $y1p);
            $cw = max(1, (int)($w * ($x2p - $x1p)));
            $ch = max(1, (int)($h * ($y2p - $y1p)));

            $crop = clone $imagick;
            $crop->cropImage($cw, $ch, $x, $y);
            $crop->setImagePage($cw, $ch, 0, 0);

            if ($ch < 160) {
                $scale = (int)ceil(160 / $ch);
                $crop->resizeImage($cw * $scale, $ch * $scale, \Imagick::FILTER_LANCZOS, 1);
            }

            $crop->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
            $crop->normalizeImage();
            $crop->setImageFormat('png');
            $imagick->clear();
            $imagick->destroy();

            // 一時ファイルに保存して tesseract を呼び出す
            $tmpPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
            $crop->writeImage($tmpPath);
            $crop->clear();
            $crop->destroy();

            $rawText = $this->runTesseract($binary, $tmpPath);
            @unlink($tmpPath);

            if ($rawText === null) {
                Log::warning('LocalTesseractService: tesseract returned no output');
                return $this->emptyResult();
            }

            Log::info('LocalTesseractService: raw OCR text', ['text' => $rawText]);

            $jobcode    = $this->parseJobcode($rawText);
            $clientName = $this->parseClientName($rawText);
            $title      = $this->parseTitle($rawText);

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
     * tesseract バイナリを実行してテキストを返す。
     * 失敗時は null を返す。
     */
    private function runTesseract(string $binary, string $imagePath): ?string
    {
        $libPath      = config('services.tesseract.lib_path', '');
        $tessdata     = config('services.tesseract.tessdata_prefix', '');

        $env = [];
        if ($libPath) {
            $env['LD_LIBRARY_PATH'] = $libPath;
        }
        if ($tessdata) {
            $env['TESSDATA_PREFIX'] = $tessdata;
        }

        $cmd = sprintf(
            '%s %s stdout -l jpn+eng 2>/dev/null',
            escapeshellarg($binary),
            escapeshellarg($imagePath)
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
