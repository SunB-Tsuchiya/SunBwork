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

        // If text file, try to generate preview via service
        try {
            $preview = $svc->generatePreviewIfText($meta);
            if ($preview) $meta['preview'] = $preview;
        } catch (\Throwable $_e) {
            Log::warning('bot preview generation failed: ' . $_e->getMessage());
        }

        // Ensure thumbnail fields are present (storeUploadedFile already attempted thumb for images)
        try {
            if (empty($meta['thumb_path']) && !empty($meta['path'])) {
                $thumb = $svc->createThumbnailFromDiskPath($meta['path']);
                if (!empty($thumb)) {
                    $meta['thumb_path'] = $thumb['thumb_path'];
                    $meta['thumb_url'] = $thumb['thumb_url'];
                }
            }
        } catch (\Throwable $__e) {
            Log::warning('bot thumbnail delegation failed: ' . $__e->getMessage());
        }

        // Format response using service helper to ensure consistency with chat
        $resp = $svc->formatResponseMeta($meta);

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
