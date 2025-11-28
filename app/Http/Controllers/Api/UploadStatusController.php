<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;

class UploadStatusController extends Controller
{
    public function status($id)
    {
        $att = Attachment::find($id);
        if (!$att) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $data = [
            'id' => $att->id,
            'status' => $att->status,
            'original_name' => $att->original_name,
            'mime' => $att->mime_type,
        ];

        if ($att->status === 'ready') {
            // prefer a temporary signed URL (good for sharing/use in emails) that expires in 15 minutes
            try {
                $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $att->path]);
                $data['url'] = $signed;
            } catch (\Exception $__e) {
                // fallback to authenticated stream route
                // Use the web-stream route so SPA clients use session-based auth
                $data['url'] = $att->path ? route('attachments.stream', ['path' => $att->path]) : null;
            }
            // public fallback
            $data['public_url'] = $att->path ? asset('storage/' . ltrim($att->path, '/')) : null;
        }

        return response()->json($data);
    }
}
