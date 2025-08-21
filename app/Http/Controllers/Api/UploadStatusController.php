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
            $data['url'] = $att->path ? asset('storage/' . ltrim($att->path, '/')) : null;
        }

        return response()->json($data);
    }
}
