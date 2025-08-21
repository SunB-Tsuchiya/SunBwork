<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessUploadJob;
use App\Models\Attachment;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        // Debug: log incoming upload attempts to help trace client requests
        try {
            $userId = $request->user()?->id ?? null;
            $fname = $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null;
            $fsize = $request->hasFile('file') ? $request->file('file')->getSize() : null;
            Log::info('UploadController@upload called', ['user_id' => $userId, 'filename' => $fname, 'size' => $fsize, 'ip' => $request->ip()]);
        } catch (\Throwable $e) {
            // ensure logging errors don't block upload flow
            Log::error('UploadController log failure', ['error' => $e->getMessage()]);
        }
        $request->validate([
            'file' => 'required|file',
            'type' => 'nullable|string'
        ]);

        $file = $request->file('file');
        // server-side size guard (allow up to 300MB)
        if ($file->getSize() > 300 * 1024 * 1024) {
            return response()->json(['error' => 'file_too_large'], 422);
        }

        // Try to store on the local disk to guarantee storage_path('app') location.
        try {
            $tmpPath = $file->store('tmp_uploads', 'local');
        } catch (\Throwable $e) {
            // Fallback: move the uploaded file directly into storage/app/tmp_uploads
            try {
                $filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
                $destDir = storage_path('app/tmp_uploads');
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0775, true);
                }
                $dest = $destDir . '/' . $filename;
                $file->move($destDir, $filename);
                $tmpPath = 'tmp_uploads/' . $filename;
            } catch (\Throwable $e2) {
                Log::error('UploadController failed to store uploaded file', ['error' => $e2->getMessage()]);
                return response()->json(['error' => 'upload_failed'], 500);
            }
        }

        // Log where the file was stored and whether it exists on disk immediately after storing.
        try {
            // clear PHP's file status cache to get up-to-date file_exists
            clearstatcache(true, storage_path('app/' . $tmpPath));
            $localExists = false;
            $localPath = null;
            try {
                $localExists = \Illuminate\Support\Facades\Storage::disk('local')->exists($tmpPath);
                // path() is available for local driver
                $localPath = \Illuminate\Support\Facades\Storage::disk('local')->path($tmpPath);
            } catch (\Throwable $_e) {
                // ignore
            }

            Log::info('UploadController stored tmp', [
                'tmpPath' => $tmpPath,
                'exists_file_exists' => file_exists(storage_path('app/' . $tmpPath)),
                'exists_storage_local' => $localExists,
                'storage_local_path' => $localPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('UploadController tmp path log failed', ['error' => $e->getMessage()]);
        }

        // create attachment record in processing state
        $attachment = Attachment::create([
            'user_id' => $request->user()?->id ?? null,
            'path' => '',
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'status' => 'processing',
            'size' => $file->getSize(),
        ]);

        // dispatch async job to process and update attachment
        ProcessUploadJob::dispatch($tmpPath, $attachment->id, $request->input('type'));

        return response()->json([
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'status' => $attachment->status,
            'size' => $attachment->size,
        ]);
    }
}
