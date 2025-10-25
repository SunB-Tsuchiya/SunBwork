<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Intervention\Image\ImageManager;

class AttachmentService
{
    /**
     * Store uploaded file into public/attachments and optionally create Attachment DB row.
     * Returns an array with keys: path, url, original_name, mime, size
     */
    public function storeUploadedFile($file, $user = null, ?string $attachableType = null, ?int $attachableId = null): array
    {
        $originalName = basename($file->getClientOriginalName());
        $safeOriginal = str_replace(['..', '/', '\\'], '_', $originalName);
        $storedName = (string) Str::uuid() . '_' . $safeOriginal;
        $path = 'attachments/' . $storedName;

        $stored = Storage::disk('public')->putFileAs('attachments', $file, $storedName);
        $url = Storage::url($path);

        $meta = [
            'path' => $path,
            'url' => $url,
            'original_name' => $originalName,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];

        // If this is an image and Intervention is available, attempt to create a thumbnail
        try {
            if (!empty($meta['mime']) && str_starts_with($meta['mime'], 'image/') && class_exists(ImageManager::class)) {
                $thumb = $this->createThumbnailFromDiskPath($meta['path']);
                if ($thumb) {
                    $meta['thumb_path'] = $thumb['thumb_path'];
                    $meta['thumb_url'] = $thumb['thumb_url'];
                }
            }
        } catch (\Throwable $__thumbEx) {
            Log::warning('AttachmentService: thumbnail generation failed: ' . $__thumbEx->getMessage());
        }

        try {
            if (class_exists(Attachment::class)) {
                $att = Attachment::create([
                    'path' => $meta['path'],
                    'original_name' => $meta['original_name'],
                    'mime_type' => $meta['mime'],
                    'size' => $meta['size'],
                    'status' => 'ready',
                    'user_id' => $user?->id,
                ]);

                // expose attachment id for callers
                $meta['attachment_id'] = $att->id;

                // attach to attachable pivot when provided and valid
                if ($attachableType && $attachableId) {
                    try {
                        if (class_exists($attachableType) && $attachableType::find($attachableId)) {
                            $now = Carbon::now();
                            DB::table('attachmentables')->insertOrIgnore([[
                                'attachment_id' => $att->id,
                                'attachable_type' => $attachableType,
                                'attachable_id' => $attachableId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]]);
                        }
                    } catch (\Throwable $__e) {
                        Log::warning('AttachmentService: failed to attach pivot (uploaded): ' . $__e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AttachmentService: could not create Attachment record: ' . $e->getMessage());
        }

        return $meta;
    }

    /**
     * Store a file that already exists on local disk into public/attachments (or folder).
     * If $storedName is null, a uuid + '_' + basename will be used.
     * If $create is true, an Attachment DB record will be created when possible.
     * Returns meta array similar to storeUploadedFile.
     */
    public function storeLocalFile(string $localPath, $user = null, string $folder = 'attachments', ?string $storedName = null, bool $create = true, ?string $attachableType = null, ?int $attachableId = null): array
    {
        if (!file_exists($localPath)) {
            throw new \RuntimeException('local file not found');
        }
        $originalName = basename($localPath);
        $safeOriginal = str_replace(['..', '/', '\\'], '_', $originalName);
        if (!$storedName) {
            $storedName = (string) Str::uuid() . '_' . $safeOriginal;
        }
        $path = rtrim($folder, '/') . '/' . $storedName;

        $moved = Storage::disk('public')->putFileAs($folder, new \Illuminate\Http\File($localPath), $storedName);
        $url = Storage::url($path);

        $meta = [
            'path' => $path,
            'url' => $url,
            'original_name' => $originalName,
            'mime' => mime_content_type($localPath) ?: null,
            'size' => filesize($localPath),
        ];

        if ($create) {
            try {
                if (class_exists(Attachment::class)) {
                    $att = Attachment::create([
                        'path' => $meta['path'],
                        'original_name' => $meta['original_name'],
                        'mime_type' => $meta['mime'],
                        'size' => $meta['size'],
                        'status' => 'ready',
                        'user_id' => $user?->id,
                    ]);

                    // expose attachment id
                    $meta['attachment_id'] = $att->id;

                    if ($attachableType && $attachableId) {
                        try {
                            if (class_exists($attachableType) && $attachableType::find($attachableId)) {
                                $now = Carbon::now();
                                DB::table('attachmentables')->insertOrIgnore([[
                                    'attachment_id' => $att->id,
                                    'attachable_type' => $attachableType,
                                    'attachable_id' => $attachableId,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]]);
                            }
                        } catch (\Throwable $__e) {
                            Log::warning('AttachmentService: failed to attach pivot (local): ' . $__e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('AttachmentService: could not create Attachment record (local): ' . $e->getMessage());
            }
        }

        return $meta;
    }

    /**
     * Normalize a path (accept bot/ or attachments/) and return storage path
     */
    public function normalizePath(string $path): string
    {
        $p = ltrim($path, '\\/.');
        if (Str::startsWith($p, 'bot/')) {
            $p = 'attachments/' . substr($p, 4);
        }
        if (!Str::startsWith($p, 'attachments/')) {
            $p = 'attachments/' . $p;
        }
        return $p;
    }

    /**
     * Create a thumbnail for a given storage path (public disk).
     * Returns ['thumb_path' => ..., 'thumb_url' => ...] on success, or null on failure.
     */
    public function createThumbnailFromDiskPath(string $path): ?array
    {
        $p = $this->normalizePath($path);
        try {
            if (!Storage::disk('public')->exists($p)) return null;
            if (!class_exists(ImageManager::class)) return null;

            $full = Storage::disk('public')->path($p);
            // build intervention manager
            if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
                $manager = ImageManager::imagick();
            } else {
                $manager = ImageManager::gd();
            }
            /** @var \Intervention\Image\Image $img */
            // use read to be consistent with other controllers (accepts path or file)
            $img = $manager->read($full);
            // resize preserving aspect ratio to fit within 400x400
            try {
                if ($img->width() > 400) {
                    $img->resize(400, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }
            } catch (\Throwable $_resizeEx) {
                // ignore resize errors
            }
            $basename = basename($p);
            $thumbPath = 'attachments/thumbs/' . $basename;

            // encode using explicit encoder to be compatible with installed Intervention version
            try {
                $thumbEncoder = new \Intervention\Image\Encoders\JpegEncoder(80);
                $thumbEncoded = $img->encode($thumbEncoder);
                // try to obtain binary data
                $thumbBin = null;
                if (method_exists($thumbEncoded, 'toDataUri')) {
                    $dataUri = $thumbEncoded->toDataUri();
                    if ($dataUri && preg_match('#^data:.*?;base64,#', $dataUri)) {
                        $thumbBin = base64_decode(preg_replace('#^data:.*?;base64,#', '', $dataUri));
                    }
                }
                if ($thumbBin === null) {
                    $thumbBin = (string) $thumbEncoded;
                }

                Storage::disk('public')->put($thumbPath, $thumbBin);
                try {
                    Storage::disk('public')->setVisibility($thumbPath, 'public');
                    $realThumb = Storage::disk('public')->path($thumbPath) ?? null;
                    if ($realThumb && file_exists($realThumb)) {
                        @chmod($realThumb, 0644);
                    }
                } catch (\Throwable $_exPerm) {
                    Log::warning('AttachmentService: thumb permission set failed', ['path' => $thumbPath, 'error' => $_exPerm->getMessage()]);
                }

                return [
                    'thumb_path' => $thumbPath,
                    'thumb_url' => Storage::url($thumbPath),
                ];
            } catch (\Throwable $__encEx) {
                Log::warning('AttachmentService: thumbnail encoding failed: ' . $__encEx->getMessage());
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('AttachmentService: createThumbnailFromDiskPath failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * If the meta represents a text file, attempt to generate a small preview string.
     * Returns preview string or null.
     */
    public function generatePreviewIfText(array $meta, int $maxChars = 2000): ?string
    {
        try {
            if (!empty($meta['preview'])) return (string) $meta['preview'];
            $mime = $meta['mime'] ?? '';
            if (empty($mime) || !str_starts_with($mime, 'text/')) return null;
            $path = $meta['path'] ?? null;
            if (!$path) return null;
            $p = $this->normalizePath($path);
            if (!Storage::disk('public')->exists($p)) return null;
            $full = Storage::disk('public')->path($p);
            if (!file_exists($full)) return null;
            $contents = @file_get_contents($full);
            if ($contents === false) return null;
            return mb_substr($contents, 0, $maxChars);
        } catch (\Throwable $e) {
            Log::warning('AttachmentService: generatePreviewIfText failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize and sanitize attachment meta for API responses.
     * Returns an array with keys like original_name, mime, size, path, url, thumb_path, thumb_url, preview.
     */
    public function formatResponseMeta(array $meta): array
    {
        $out = [];
        $out['original_name'] = isset($meta['original_name']) ? substr((string) $meta['original_name'], 0, 255) : '';
        $out['mime'] = isset($meta['mime']) ? substr((string) $meta['mime'], 0, 100) : '';
        $out['size'] = isset($meta['size']) ? intval($meta['size']) : 0;

        // prefer normalized storage path when present
        if (!empty($meta['path'])) {
            $p = $this->normalizePath((string) $meta['path']);
            if (Storage::disk('public')->exists($p)) {
                $out['path'] = $p;
                $out['url'] = Storage::url($p);
            } else {
                // If stored path doesn't exist, still expose the given path and url if present
                $out['path'] = (string) $meta['path'];
                $out['url'] = $meta['url'] ?? null;
            }
        } elseif (!empty($meta['url'])) {
            $out['url'] = $meta['url'];
        }

        if (!empty($meta['thumb_path'])) {
            $out['thumb_path'] = (string) $meta['thumb_path'];
        }
        if (!empty($meta['thumb_url'])) {
            $out['thumb_url'] = (string) $meta['thumb_url'];
        }
        if (!empty($meta['preview'])) {
            $out['preview'] = (string) $meta['preview'];
        }
        if (!empty($meta['attachment_id'])) {
            $out['attachment_id'] = $meta['attachment_id'];
        }

        return $out;
    }

    /**
     * Stream file by path (returns full disk path)
     */
    public function diskPath(string $path): string
    {
        $p = $this->normalizePath($path);
        if (!Storage::disk('public')->exists($p)) {
            throw new \RuntimeException('file not found');
        }
        return Storage::disk('public')->path($p);
    }

    /**
     * Delete file and attachment DB row if present. Returns bool deleted.
     */
    public function deleteByPath(string $path): bool
    {
        $p = $this->normalizePath($path);
        // If an Attachment DB row exists for this path, prefer full cleanup via deleteAttachment
        try {
            if (class_exists(Attachment::class)) {
                $att = Attachment::where('path', $p)->first();
                if ($att) {
                    return $this->deleteAttachment($att);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AttachmentService: error looking up Attachment by path: ' . $e->getMessage());
        }

        // Fallback: delete storage file only and attempt to remove any DB rows that match the path
        if (!Storage::disk('public')->exists($p)) return false;
        $deleted = Storage::disk('public')->delete($p);
        if ($deleted) {
            try {
                if (class_exists(Attachment::class)) {
                    Attachment::where('path', $p)->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('AttachmentService: failed to delete Attachment DB row (fallback): ' . $e->getMessage());
            }
        }
        return (bool) $deleted;
    }

    /**
     * Perform a complete deletion of an Attachment: storage files (main + common thumbs),
     * linked attachable models (safely for chat/file messages), pivot rows, and the DB row.
     * Returns true on success, false on failure.
     */
    public function deleteAttachment(Attachment $attachment, $requestingUser = null): bool
    {
        $id = $attachment->id ?? null;
        $path = $attachment->path ?? null;
        if (!$path) {
            Log::warning('AttachmentService::deleteAttachment called with attachment missing path', ['attachment_id' => $id]);
        }

        // Build list of candidate files to remove (main file + common thumbnail variants)
        $candidates = [];
        if ($path) {
            $basename = basename($path);
            $dir = trim(dirname($path), '\\/');
            $candidates[] = $path;
            $candidates[] = $dir . '/thumbs/' . $basename;
            $candidates[] = 'attachments/thumbs/' . $basename;
            $candidates[] = $dir . '/thumb_' . $basename;
            $candidates[] = $dir . '/thumb-' . $basename;
            $candidates[] = $dir . '/small_' . $basename;
            $candidates[] = 'thumbs/' . $basename;
        }

        // Delete files (ignore errors)
        foreach (array_unique(array_filter($candidates)) as $p) {
            try {
                if (Storage::disk('public')->exists($p)) {
                    Storage::disk('public')->delete($p);
                }
            } catch (\Throwable $e) {
                Log::warning('AttachmentService: failed deleting storage candidate: ' . $e->getMessage(), ['path' => $p, 'attachment_id' => $id]);
            }
        }

        // Find and delete attachable models linked via pivot when safe
        try {
            $pivots = DB::table('attachmentables')->where('attachment_id', $id)->get();
            foreach ($pivots as $piv) {
                try {
                    if (!empty($piv->attachable_type) && !empty($piv->attachable_id) && class_exists($piv->attachable_type)) {
                        $attachableClass = $piv->attachable_type;
                        $attachableId = $piv->attachable_id;
                        // Only attempt to delete ChatMessage-like models to avoid accidentally deleting diaries/events
                        if ($attachableClass === \App\Models\ChatMessage::class || $attachableClass === 'App\\Models\\ChatMessage') {
                            $model = $attachableClass::find($attachableId);
                            if ($model) {
                                try {
                                    $model->delete();
                                } catch (\Throwable $__e) {
                                    Log::warning('AttachmentService: could not delete linked ChatMessage: ' . $__e->getMessage(), ['attachment_id' => $id, 'message_id' => $attachableId]);
                                }
                            }
                        } else {
                            // Best-effort: delete attachable if it has file-like type marker to avoid deleting non-file resources
                            try {
                                $model = $attachableClass::find($attachableId);
                                if ($model && isset($model->type) && ($model->type === 'file' || $model->type === 'attachment')) {
                                    $model->delete();
                                }
                            } catch (\Throwable $__e) {
                                // ignore non-fatal
                            }
                        }
                    }
                } catch (\Throwable $_inner) {
                    Log::warning('AttachmentService: error deleting attachable: ' . $_inner->getMessage(), ['attachment_id' => $id, 'pivot' => $piv]);
                }
            }
        } catch (\Throwable $_e) {
            Log::warning('AttachmentService: linked attachable cleanup failed: ' . $_e->getMessage(), ['attachment_id' => $id]);
        }

        // Remove pivot rows
        try {
            DB::table('attachmentables')->where('attachment_id', $id)->delete();
        } catch (\Throwable $_e) {
            Log::warning('AttachmentService: could not delete pivot rows: ' . $_e->getMessage(), ['attachment_id' => $id]);
        }

        // Delete attachment DB row
        try {
            Attachment::where('id', $id)->delete();
        } catch (\Throwable $__e) {
            Log::warning('AttachmentService: could not delete attachment row: ' . $__e->getMessage(), ['attachment_id' => $id]);
            return false;
        }

        return true;
    }

    /**
     * Attach an existing attachment to an attachable model via pivot
     */
    public function attachPivot(int $attachmentId, string $attachableType, int $attachableId): bool
    {
        try {
            if (!class_exists($attachableType)) return false;
            if (!$attachableType::find($attachableId)) return false;
            $now = Carbon::now();
            DB::table('attachmentables')->insertOrIgnore([[
                'attachment_id' => $attachmentId,
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('AttachmentService: attachPivot failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create pivots for legacy columns on the attachments table if present.
     */
    public function ensurePivotFromLegacy(Attachment $a): void
    {
        try {
            $hasMessage = \Illuminate\Support\Facades\Schema::hasColumn('attachments', 'message_id');
            $hasDiary = \Illuminate\Support\Facades\Schema::hasColumn('attachments', 'diary_id');
            $hasEvent = \Illuminate\Support\Facades\Schema::hasColumn('attachments', 'event_id');
            $hasOwner = \Illuminate\Support\Facades\Schema::hasColumn('attachments', 'owner_type') && \Illuminate\Support\Facades\Schema::hasColumn('attachments', 'owner_id');
        } catch (\Throwable $_scEx) {
            return;
        }

        $now = Carbon::now();
        $toInsert = [];
        if (!empty($hasMessage) && ($a->message_id ?? null)) {
            $toInsert[] = [
                'attachment_id' => $a->id,
                'attachable_type' => \App\Models\Message::class,
                'attachable_id' => $a->message_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($hasDiary) && ($a->diary_id ?? null)) {
            $toInsert[] = [
                'attachment_id' => $a->id,
                'attachable_type' => \App\Models\Diary::class,
                'attachable_id' => $a->diary_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($hasEvent) && ($a->event_id ?? null)) {
            $toInsert[] = [
                'attachment_id' => $a->id,
                'attachable_type' => \App\Models\Event::class,
                'attachable_id' => $a->event_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($hasOwner) && ($a->owner_type ?? null) && ($a->owner_id ?? null)) {
            $allowed = [\App\Models\Diary::class, \App\Models\Event::class, \App\Models\Message::class, \App\Models\User::class];
            if (in_array($a->owner_type, $allowed, true)) {
                $toInsert[] = [
                    'attachment_id' => $a->id,
                    'attachable_type' => $a->owner_type,
                    'attachable_id' => $a->owner_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            $chunks = array_chunk($toInsert, 200);
            foreach ($chunks as $chunk) {
                DB::table('attachmentables')->insertOrIgnore($chunk);
            }
        }
    }
}
