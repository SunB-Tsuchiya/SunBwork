<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class PrepressImageService
{
    // Max width (px). Height scales proportionally.
    const MAX_WIDTH = 1600;
    // JPEG quality (0-100). 85 balances readability and file size.
    const JPEG_QUALITY = 85;

    const STORAGE_DIR = 'prepress/jobticker';

    /**
     * Convert an uploaded image file to JPG, resize if needed, and store it.
     * Returns the stored path relative to the public disk root,
     * or null on failure (caller should handle gracefully).
     */
    public function convertAndStore(UploadedFile $file): ?array
    {
        try {
            $manager = $this->resolveManager();
            if (!$manager) {
                return null;
            }

            $img = $manager->read($file->getRealPath());

            // Resize only if wider than MAX_WIDTH (preserve aspect ratio)
            if ($img->width() > self::MAX_WIDTH) {
                $img->scale(width: self::MAX_WIDTH);
            }

            $filename = Str::uuid() . '.jpg';
            $storagePath = self::STORAGE_DIR . '/' . $filename;

            $encoded = $img->toJpeg(self::JPEG_QUALITY);

            Storage::disk('public')->put($storagePath, (string) $encoded);

            return [
                'path'              => $storagePath,
                'url'               => Storage::disk('public')->url($storagePath),
                'original_filename' => $file->getClientOriginalName(),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PrepressImageService: image conversion failed: ' . $e->getMessage());
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
