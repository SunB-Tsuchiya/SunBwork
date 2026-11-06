<?php

namespace App\Services\Portfolio;

use Imagick;
use ImagickPixel;
use RuntimeException;

/**
 * 画像の縮小。長辺 2400px・JPEG 品質 82・EXIF 向き補正・メタ情報除去、サムネイルは長辺 600px。
 * 原本は保存しない（容量節約。PLAN §5.1）。
 */
class ImageProcessor
{
    public const MAX_EDGE = 2400;
    public const THUMB_EDGE = 600;

    /**
     * @return array{main: string, thumb: string, width: int, height: int} JPEG バイナリと寸法
     */
    public function process(string $absolutePath): array
    {
        if (! class_exists(Imagick::class)) {
            throw new RuntimeException('Imagick が利用できません。');
        }

        $im = new Imagick();
        $im->readImage($absolutePath . '[0]'); // アニメ画像・多ページは先頭だけ
        $this->fixOrientation($im);

        // 透過 PNG は白背景に敷く（JPEG 化のため）
        $im->setImageBackgroundColor(new ImagickPixel('white'));
        $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $im->setImageColorspace(Imagick::COLORSPACE_SRGB);

        if (max($im->getImageWidth(), $im->getImageHeight()) > self::MAX_EDGE) {
            $im->thumbnailImage(self::MAX_EDGE, self::MAX_EDGE, true);
        }
        $main = $this->toJpeg($im, 82);
        $width = $im->getImageWidth();
        $height = $im->getImageHeight();

        $thumb = clone $im;
        $thumb->thumbnailImage(self::THUMB_EDGE, self::THUMB_EDGE, true);
        $thumbBlob = $this->toJpeg($thumb, 78);

        $im->clear();
        $thumb->clear();

        return ['main' => $main, 'thumb' => $thumbBlob, 'width' => $width, 'height' => $height];
    }

    private function toJpeg(Imagick $im, int $quality): string
    {
        $im->setImageFormat('jpeg');
        $im->setImageCompression(Imagick::COMPRESSION_JPEG);
        $im->setImageCompressionQuality($quality);
        $im->setInterlaceScheme(Imagick::INTERLACE_PLANE); // プログレッシブ
        $im->stripImage();

        return $im->getImagesBlob();
    }

    private function fixOrientation(Imagick $im): void
    {
        switch ($im->getImageOrientation()) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $im->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $im->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $im->rotateImage('#000', -90);
                break;
        }
        $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }
}
