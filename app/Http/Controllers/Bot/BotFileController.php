<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use App\Services\AttachmentService;

class BotFileController extends Controller
{
    // POST /bot/files - upload a file for the bot
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        // Preserve original filename when saving. If a file with the same name
        // already exists in the 'bot' folder on the public disk, return 409 so
        // the client can notify the user rather than silently renaming.
        $originalName = basename($file->getClientOriginalName());
        $svc = new AttachmentService();
        $attachableType = $request->input('attachable_type');
        $attachableId = $request->input('attachable_id') ? intval($request->input('attachable_id')) : null;
        $meta = $svc->storeUploadedFile($file, $request->user(), $attachableType, $attachableId);

        // try to create small text preview for text files
        if (str_starts_with($file->getClientMimeType(), 'text/')) {
            try {
                $contents = file_get_contents($file->getRealPath());
                $meta['preview'] = mb_substr($contents, 0, 2000);
            } catch (\Exception $e) {
                Log::warning('bot file preview failed: ' . $e->getMessage());
            }
        }

        // Delegate thumbnail creation to AttachmentService so bot and chat share the same logic
        try {
            $thumbResult = $svc->createThumbnailFromDiskPath($meta['path']);
            if (!empty($thumbResult)) {
                $meta['thumb_path'] = $thumbResult['thumb_path'];
                $meta['thumb_url'] = $thumbResult['thumb_url'];
            }
        } catch (\Throwable $__e) {
            Log::warning('bot thumbnail delegation failed: ' . $__e->getMessage());
        }

        // sanitize response meta: only include allowed keys and safe url/path
        $safe = [
            'original_name' => substr($meta['original_name'] ?? '', 0, 255),
            'mime' => substr($meta['mime'] ?? '', 0, 100),
            'size' => intval($meta['size'] ?? 0),
            'path' => $meta['path'],
            'url' => Storage::url($meta['path']),
        ];

        // Build response and include thumb metadata when present
        $resp = [
            'original_name' => substr($meta['original_name'] ?? '', 0, 255),
            'mime' => substr($meta['mime'] ?? '', 0, 100),
            'size' => intval($meta['size'] ?? 0),
            'path' => $meta['path'],
            'url' => $meta['url'] ?? Storage::url($meta['path']),
        ];
        if (!empty($meta['thumb_path'])) $resp['thumb_path'] = $meta['thumb_path'];
        if (!empty($meta['thumb_url'])) $resp['thumb_url'] = $meta['thumb_url'];
        if (!empty($meta['preview'])) $resp['preview'] = $meta['preview'];

        return response()->json(['file' => $resp]);
    }

    // GET /bot/attachments?path=bot/xxx - stream file back
    public function stream(Request $request)
    {
        $path = $request->query('path');
        if (!$path) abort(400, 'path is required');
        $svc = new AttachmentService();
        try {
            $full = $svc->diskPath($path);
            return response()->file($full);
        } catch (\RuntimeException $e) {
            abort(404);
        }
    }

    // POST /bot/files/delete - delete an uploaded file (authenticated users)
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);
        $path = $request->input('path');
        $svc = new AttachmentService();
        try {
            $deleted = $svc->deleteByPath($path);
            if ($deleted) return response()->json(['success' => true]);
            return response()->json(['error' => 'file not found'], 404);
        } catch (\Exception $e) {
            Log::error('bot file delete failed: ' . $e->getMessage());
            return response()->json(['error' => 'delete error'], 500);
        }
    }
}
