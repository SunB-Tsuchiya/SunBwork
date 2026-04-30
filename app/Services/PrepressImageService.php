<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class PrepressImageService
{
    // 通常画像: 幅の上限 (px)
    const MAX_WIDTH = 1800;

    // JPEG品質 (0-100)
    const JPEG_QUALITY = 92;

    // PDF レンダリング DPI
    // A4 @ 200 DPI = 1654 × 2339 px → 約400〜700KB で細部まで読める
    const PDF_DPI = 200;

    const STORAGE_DIR = 'prepress/jobticker';

    /**
     * アップロードされたファイルをJPGに変換・保存する。
     * PDFはImagickで1ページ目を200 DPIでラスタライズしてから保存する。
     */
    public function convertAndStore(UploadedFile $file): ?array
    {
        try {
            if ($this->isPdf($file)) {
                return $this->convertPdfAndStore($file);
            }
            return $this->convertImageAndStore($file);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PrepressImageService: conversion failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a stored image by its path.
     */
    public function delete(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable) {
            // non-fatal
        }
    }

    // ── private ──────────────────────────────────────────────────

    private function isPdf(UploadedFile $file): bool
    {
        $mime = $file->getMimeType();
        $ext  = strtolower($file->getClientOriginalExtension());
        return $mime === 'application/pdf' || $ext === 'pdf';
    }

    /**
     * PDFの1ページ目をImagickで高解像度ラスタライズしてJPGに変換する。
     * Imagickが使えない場合は null を返す（呼び出し元でエラーハンドリング）。
     */
    private function convertPdfAndStore(UploadedFile $file): ?array
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            \Illuminate\Support\Facades\Log::warning('PrepressImageService: Imagick not available for PDF conversion.');
            return null;
        }

        $imagick = new \Imagick();
        // DPIをreadImage前に設定しないと反映されない
        $imagick->setResolution(self::PDF_DPI, self::PDF_DPI);
        // [0] = 1ページ目のみ取得
        $imagick->readImage($file->getRealPath() . '[0]');

        // 透過・レイヤーを白背景に統合
        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $imagick->setImageBackgroundColor('white');
        $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        // 幅がMAX_WIDTHを超える場合のみリサイズ
        $w = $imagick->getImageWidth();
        $h = $imagick->getImageHeight();
        if ($w > self::MAX_WIDTH) {
            $newH = (int) round($h * self::MAX_WIDTH / $w);
            $imagick->resizeImage(self::MAX_WIDTH, $newH, \Imagick::FILTER_LANCZOS, 1);
        }

        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(self::JPEG_QUALITY);
        $jpegBlob = $imagick->getImageBlob();
        $imagick->clear();
        $imagick->destroy();

        $filename    = Str::uuid() . '.jpg';
        $storagePath = self::STORAGE_DIR . '/' . $filename;
        Storage::disk('public')->put($storagePath, $jpegBlob);

        return [
            'path'              => $storagePath,
            'url'               => Storage::disk('public')->url($storagePath),
            'original_filename' => $file->getClientOriginalName(),
        ];
    }

    /**
     * 通常の画像ファイル（JPG/PNG/WEBP/HEIC等）をJPGに変換する。
     */
    private function convertImageAndStore(UploadedFile $file): ?array
    {
        $manager = $this->resolveManager();
        if (!$manager) {
            return null;
        }

        $img = $manager->read($file->getRealPath());

        if ($img->width() > self::MAX_WIDTH) {
            $img->scale(width: self::MAX_WIDTH);
        }

        $filename    = Str::uuid() . '.jpg';
        $storagePath = self::STORAGE_DIR . '/' . $filename;
        $encoded     = $img->toJpeg(self::JPEG_QUALITY);
        Storage::disk('public')->put($storagePath, (string) $encoded);

        return [
            'path'              => $storagePath,
            'url'               => Storage::disk('public')->url($storagePath),
            'original_filename' => $file->getClientOriginalName(),
        ];
    }

    private function resolveManager(): ?ImageManager
    {
        if (!class_exists(ImageManager::class)) {
            return null;
        }
        if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
            return ImageManager::imagick();
        }
        return ImageManager::gd();
    }
}
