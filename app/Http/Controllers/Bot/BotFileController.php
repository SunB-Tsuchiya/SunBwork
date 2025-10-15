<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
        // sanitize filename to remove any directory separators
        $originalName = str_replace(['..', '/', '\\'], '_', $originalName);
        $path = 'bot/' . $originalName;
        if (Storage::disk('public')->exists($path)) {
            return response()->json([
                'error' => 'file_exists',
                'message' => '同名のファイルが既に存在します: ' . $originalName,
                'path' => $path,
            ], 409);
        }

        // store using original name
        $stored = Storage::disk('public')->putFileAs('bot', $file, $originalName);
        $url = Storage::url($path);
        $meta = [
            'url' => $url,
            'original_name' => $originalName,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ];

        // try to create small text preview for text files
        if (str_starts_with($file->getClientMimeType(), 'text/')) {
            try {
                $contents = file_get_contents($file->getRealPath());
                $meta['preview'] = mb_substr($contents, 0, 2000);
            } catch (\Exception $e) {
                Log::warning('bot file preview failed: ' . $e->getMessage());
            }
        }

        // sanitize response meta: only include allowed keys and safe url/path
        $safe = [
            'original_name' => substr($meta['original_name'] ?? '', 0, 255),
            'mime' => substr($meta['mime'] ?? '', 0, 100),
            'size' => intval($meta['size'] ?? 0),
            'path' => $meta['path'],
            'url' => Storage::url($meta['path']),
        ];
        if (!Storage::disk('public')->exists($safe['path'])) {
            return response()->json(['error' => 'file not found after upload'], 500);
        }
        return response()->json(['file' => $safe]);
    }

    // GET /bot/attachments?path=bot/xxx - stream file back
    public function stream(Request $request)
    {
        $user = $request->user();
        $path = $request->query('path');
        if (!$path) abort(400, 'path is required');
        $path = ltrim($path, '\\/');
        if (!str_starts_with($path, 'bot/')) {
            $path = 'bot/' . $path;
        }
        if (!Storage::disk('public')->exists($path)) abort(404);
        $full = Storage::disk('public')->path($path);
        return response()->file($full);
    }

    // POST /bot/files/delete - delete an uploaded file (authenticated users)
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);
        $path = $request->input('path');
        $path = ltrim($path, '\\/');
        if (!str_starts_with($path, 'bot/')) {
            $path = 'bot/' . $path;
        }
        try {
            if (!Storage::disk('public')->exists($path)) {
                return response()->json(['error' => 'file not found'], 404);
            }
            $deleted = Storage::disk('public')->delete($path);
            if ($deleted) {
                return response()->json(['success' => true]);
            }
            return response()->json(['error' => 'delete failed'], 500);
        } catch (\Exception $e) {
            Log::error('bot file delete failed: ' . $e->getMessage());
            return response()->json(['error' => 'delete error'], 500);
        }
    }
}
