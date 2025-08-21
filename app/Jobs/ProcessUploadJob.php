<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use App\Models\Attachment;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tmpPath;
    protected $attachmentId;
    protected $type;

    public function __construct(string $tmpPath, int $attachmentId, ?string $type = null)
    {
        $this->tmpPath = $tmpPath;
        $this->attachmentId = $attachmentId;
        $this->type = $type;
    }

    public function handle()
    {
        $tmpPath = $this->tmpPath;

        // Resolve the actual filesystem path for the tmp file robustly.
        $fullTmp = null;
        $storageLocalPath = null;
        $storageLocalExists = false;

        // 1) Try using the local disk path() (most accurate when local driver is used)
        try {
            $storageLocalExists = Storage::disk('local')->exists($tmpPath);
            if ($storageLocalExists) {
                $storageLocalPath = Storage::disk('local')->path($tmpPath);
                if ($storageLocalPath && file_exists($storageLocalPath)) {
                    $fullTmp = $storageLocalPath;
                }
            }
        } catch (\Throwable $_e) {
            // ignore and continue to candidate checks
        }

        // 2) If not found yet, try several reasonable filesystem candidates
        if (!$fullTmp) {
            $candidates = [
                storage_path('app/' . $tmpPath),
                storage_path('app/private/' . $tmpPath),
                storage_path('app/private/' . basename($tmpPath)),
                // backward-compat: plain basename under tmp_uploads
                storage_path('app/tmp_uploads/' . basename($tmpPath)),
            ];

            foreach ($candidates as $candidate) {
                try {
                    clearstatcache(true, $candidate);
                } catch (\Throwable $_e) {
                    // ignore
                }
                if (file_exists($candidate)) {
                    $fullTmp = $candidate;
                    break;
                }
            }

            // 3) As a last attempt, ask the local disk for the path of a "private/" prefixed key
            if (!$fullTmp) {
                try {
                    if (Storage::disk('local')->exists('private/' . $tmpPath)) {
                        $storageLocalExists = true;
                        $storageLocalPath = Storage::disk('local')->path('private/' . $tmpPath);
                        if ($storageLocalPath && file_exists($storageLocalPath)) {
                            $fullTmp = $storageLocalPath;
                        }
                    }
                } catch (\Throwable $_e) {
                    // ignore
                }
            }

            // If still not found, default to the primary candidate for logging and later failure handling
            if (!$fullTmp) {
                $fullTmp = storage_path('app/' . $tmpPath);
            }
        }

        // clear PHP's file status cache and log job start and file existence for debugging
        try {
            clearstatcache(true, $fullTmp);
            logger()->info('ProcessUploadJob start', [
                'attachment_id' => $this->attachmentId,
                'tmpPath' => $tmpPath,
                'fullTmp' => $fullTmp,
                'exists_file_exists' => file_exists($fullTmp),
                'storage_local_exists' => $storageLocalExists,
                'storage_local_path' => $storageLocalPath,
            ]);
        } catch (\Throwable $e) {
            logger()->error('ProcessUploadJob start log failed', ['error' => $e->getMessage()]);
        }
        if (!file_exists($fullTmp)) {
            Attachment::where('id', $this->attachmentId)->update(['status' => 'failed']);
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullTmp);
        finfo_close($finfo);

        $originalName = basename($tmpPath);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: '';

        $denyExt = ['php','php3','php4','phtml','exe','sh','bat','cmd','scr'];
        if (in_array(strtolower($ext), $denyExt)) {
            Attachment::where('id', $this->attachmentId)->update(['status' => 'rejected']);
            @unlink($fullTmp);
            return;
        }

        $folder = 'attachments';
        if ($this->type) {
            $folder = trim($this->type, '/') . '/attachments';
        }

        $unique = uniqid() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
        $finalPath = $folder . '/' . $unique;

        if (strpos($mime, 'image/') === 0) {
            // Try Intervention Image via ImageManager
            try {
                if (class_exists(\Intervention\Image\ImageManager::class)) {
                    // Use v3 factory helpers: prefer imagick driver when available
                    if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
                        $manager = ImageManager::imagick();
                    } else {
                        $manager = ImageManager::gd();
                    }
                    $img = $manager->read($fullTmp);
                    if ($img->width() > 600) {
                        $img->resize(600, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    // Use v3 encoders
                    if (strtolower($ext) === 'png') {
                        $encoder = new \Intervention\Image\Encoders\PngEncoder();
                    } else {
                        $encoder = new \Intervention\Image\Encoders\JpegEncoder(80);
                    }
                    $encoded = $img->encode($encoder);
                        Storage::disk('public')->put($finalPath, (string) $encoded->toDataUri() ? base64_decode(preg_replace('#^data:.*?;base64,#', '', $encoded->toDataUri())) : (string) $encoded);
                        // ensure public visibility and file permissions when using local driver
                        try {
                            Storage::disk('public')->setVisibility($finalPath, 'public');
                            $real = Storage::disk('public')->path($finalPath) ?? null;
                            if ($real && file_exists($real)) {
                                @chmod($real, 0644);
                            }
                        } catch (\Throwable $_permEx) {
                            logger()->warning('ProcessUploadJob: could not set permissions', [
                                'attachment_id' => $this->attachmentId,
                                'path' => $finalPath,
                                'error' => $_permEx->getMessage(),
                            ]);
                        }
                    $size = Storage::disk('public')->size($finalPath);
                    @unlink($fullTmp);
                    Attachment::where('id', $this->attachmentId)->update([
                        'path' => $finalPath,
                        'mime_type' => $mime,
                        'size' => $size,
                        'status' => 'ready',
                    ]);
                    return;
                }
            } catch (\Exception $e) {
                logger()->error('ProcessUploadJob image processing (Intervention) failed', [
                    'attachment_id' => $this->attachmentId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Fall through to GD fallback
            }

            // GD fallback
            try {
                $data = @file_get_contents($fullTmp);
                if ($data === false) {
                    throw new \RuntimeException('Could not read tmp file');
                }
                $src = @imagecreatefromstring($data);
                if (!$src) {
                    throw new \RuntimeException('imagecreatefromstring failed');
                }
                $width = imagesx($src);
                $height = imagesy($src);
                $targetWidth = $width > 600 ? 600 : $width;
                $targetHeight = (int) floor($height * ($targetWidth / $width));
                $dst = imagecreatetruecolor($targetWidth, $targetHeight);
                // Preserve transparency for PNG
                if (str_contains($mime, 'png')) {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                    imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                ob_start();
                if (str_contains($mime, 'png')) {
                    imagepng($dst, null, 6);
                } else {
                    imagejpeg($dst, null, 80);
                }
                $out = ob_get_clean();
                imagedestroy($src);
                imagedestroy($dst);
                if ($out === false) {
                    throw new \RuntimeException('Encoding resized image failed');
                }
                Storage::disk('public')->put($finalPath, $out);
                $size = Storage::disk('public')->size($finalPath);
                @unlink($fullTmp);
                Attachment::where('id', $this->attachmentId)->update([
                    'path' => $finalPath,
                    'mime_type' => $mime,
                    'size' => $size,
                    'status' => 'ready',
                ]);
                return;
            } catch (\Exception $e) {
                logger()->error('ProcessUploadJob image processing (GD fallback) failed', [
                    'attachment_id' => $this->attachmentId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                Attachment::where('id', $this->attachmentId)->update(['status' => 'failed']);
                @unlink($fullTmp);
                return;
            }
        }

        $moved = Storage::disk('public')->putFileAs($folder, new \Illuminate\Http\File($fullTmp), $unique);
        if ($moved) {
            $size = Storage::disk('public')->size($moved);
            @unlink($fullTmp);
            Attachment::where('id', $this->attachmentId)->update([
                'path' => $moved,
                'mime_type' => $mime,
                'size' => $size,
                'status' => 'ready',
            ]);
            return;
        }
        // If we reached here, moving failed. Log and mark failed.
        logger()->error('ProcessUploadJob could not move file to storage', [
            'attachment_id' => $this->attachmentId,
            'tmpPath' => $tmpPath,
            'fullTmp' => $fullTmp,
        ]);
        Attachment::where('id', $this->attachmentId)->update(['status' => 'failed']);
        @unlink($fullTmp);
    }
}