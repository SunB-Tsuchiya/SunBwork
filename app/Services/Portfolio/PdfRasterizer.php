<?php

namespace App\Services\Portfolio;

use Imagick;
use ImagickPixel;
use RuntimeException;

/**
 * PDF のページ画像化（Imagick + Ghostscript。さくらでも利用可能なことを SSH で確認済み）。
 * 長辺 2000px・150dpi 相当・JPEG 品質 80。Web の実行時間上限対策として、1 回の呼び出しで数ページずつ処理する想定。
 */
class PdfRasterizer
{
    /** 取り込める最大ページ数（PLAN §5.2） */
    public const MAX_PAGES = 60;
    /** 1 回の process 呼び出しで変換するページ数 */
    public const PAGES_PER_CALL = 4;
    public const DPI = 150;
    public const MAX_EDGE = 2000;
    public const THUMB_EDGE = 600;

    /** ページ数を数える（読み込まずに情報だけ取る ping。失敗したら PDF 内の /Type /Page を数える） */
    public function countPages(string $absolutePath): int
    {
        try {
            $im = new Imagick();
            $im->pingImage($absolutePath);
            $n = $im->getNumberImages();
            $im->clear();
            if ($n > 0) {
                return $n;
            }
        } catch (\Throwable $e) {
            // フォールバックへ
        }

        $raw = file_get_contents($absolutePath);

        return (int) preg_match_all('#/Type\s*/Page(?![s\w])#', $raw);
    }

    /**
     * 指定ページ（0 始まり）を JPEG にする。
     *
     * @return array{blob: string, width: int, height: int}
     */
    public function renderPage(string $absolutePath, int $index): array
    {
        if (! class_exists(Imagick::class)) {
            throw new RuntimeException('Imagick が利用できません。');
        }

        $im = new Imagick();
        $im->setResolution(self::DPI, self::DPI);
        $im->readImage($absolutePath . '[' . $index . ']');

        // 透過のあるページは白背景に敷く（JPEG 化のため）
        $im->setImageBackgroundColor(new ImagickPixel('white'));
        $flat = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $im->clear();
        $flat->setImageColorspace(Imagick::COLORSPACE_SRGB);

        if (max($flat->getImageWidth(), $flat->getImageHeight()) > self::MAX_EDGE) {
            $flat->thumbnailImage(self::MAX_EDGE, self::MAX_EDGE, true);
        }
        $flat->setImageFormat('jpeg');
        $flat->setImageCompression(Imagick::COMPRESSION_JPEG);
        $flat->setImageCompressionQuality(80);
        $flat->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        $flat->stripImage();

        $out = ['blob' => $flat->getImagesBlob(), 'width' => $flat->getImageWidth(), 'height' => $flat->getImageHeight()];
        $flat->clear();

        return $out;
    }

    /** ページ画像（JPEG バイナリ）からサムネイルを作る */
    public function thumbnail(string $jpegBlob): string
    {
        $im = new Imagick();
        $im->readImageBlob($jpegBlob);
        $im->thumbnailImage(self::THUMB_EDGE, self::THUMB_EDGE, true);
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(78);
        $im->stripImage();
        $blob = $im->getImagesBlob();
        $im->clear();

        return $blob;
    }
}
