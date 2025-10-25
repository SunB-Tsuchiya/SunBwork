<?php
// ---------------------------------------------------------------------------
// SPA authentication note
// ---------------------------------------------------------------------------
// This application serves a first-party SPA (Inertia / Vite). For SPA-originated
// XHRs we rely on Laravel session cookies (laravel_session) + XSRF-TOKEN for
// authentication. The default `api` middleware group is stateless and does not
// start the session; therefore SPA-facing endpoints must either be routed
// through the `web` middleware (so StartSession runs) or explicitly include
// the necessary session middleware.
//
// Decision taken here: keep SPA-facing endpoints (those the browser calls
// directly with first-party cookies) on the `web` middleware so they can use
// session-based `auth:sanctum` authentication. Non-SPA API endpoints that are
// truly token/bearer-based may remain stateless under the API middleware.
//
// See .github/PULL_REQUEST_TEMPLATE.md for PR description and verification
// steps.
// ---------------------------------------------------------------------------
// チャットメッセージ既読登録
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use App\Models\Message;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web', 'auth:sanctum']);

// Note: SPA-first endpoints use session-auth via the `web` middleware so that
// browser first-party cookies (laravel_session/XSRF-TOKEN) authenticate SPA XHRs.

use App\Models\Client;
// クライアント一覧API（id, nameのみ返す）
Route::get('/clients', function () {
    return Client::select('id', 'name')->get();
});

Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/chat-rooms', [\App\Http\Controllers\Chat\ChatController::class, 'indexRooms']);
    Route::post('/chat-rooms', [\App\Http\Controllers\Chat\ChatController::class, 'storeRoom']);
    Route::get('/chat-rooms/{id}', [\App\Http\Controllers\Chat\ChatController::class, 'showRoom']);
    Route::put('/chat-rooms/{id}', [\App\Http\Controllers\Chat\ChatController::class, 'updateRoom']);
    Route::delete('/chat-rooms/{id}', [\App\Http\Controllers\Chat\ChatController::class, 'destroyRoom']);
    // Unified upload endpoint for diary/chat attachments
    Route::post('/uploads', [\App\Http\Controllers\Api\UploadController::class, 'upload']);
    Route::get('/uploads/status/{id}', [\App\Http\Controllers\Api\UploadStatusController::class, 'status']);
    // Fetch attachment metadata by id (used by MessageArea to resolve attachment_id)
    Route::get('/attachments/{id}', [\App\Http\Controllers\Api\UploadController::class, 'showAttachment']);
    // Stream attachment with authorization
    Route::get('/attachments/stream', [\App\Http\Controllers\AttachmentController::class, 'stream'])->name('api.attachments.stream');

    // Debug: return the same mapping MessageController::show would produce for a message
    // Access while logged-in at /api/debug/messages/{id}/payload
    Route::get('/debug/messages/{message}/payload', function (Request $request, Message $message) {
        Gate::authorize('view', $message);
        $message->load('fromUser', 'recipients.user', 'attachments');

        $mapped = $message->toArray();
        $mapped['attachments'] = $message->attachments->map(function ($att) {
            $url = null;
            $public = null;
            if ($att->status === 'ready' && $att->path) {
                try {
                    $url = route('api.attachments.stream', ['path' => $att->path]);
                    $public = asset('storage/' . ltrim($att->path, '/'));
                } catch (\Throwable $__e) {
                    $url = null;
                    $public = null;
                }
            }
            return [
                'id' => $att->id,
                'original_name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'size' => $att->size,
                'status' => $att->status,
                'url' => $url,
                'public_url' => $public,
                'path' => $att->path,
            ];
        })->values();

        return response()->json($mapped);
    })->name('debug.messages.payload');

    // ...existing code in this group...
});

// Local-only public debug payload (no auth) for developer browser fetches on local machine.
// Accessible only when running in local environment or from loopback to avoid exposure.
Route::get('/debug/public/messages/{message}/payload', function (Request $request, Message $message) {
    if (!app()->environment('local') && $request->ip() !== '127.0.0.1' && $request->ip() !== '::1') {
        abort(404);
    }
    $message->load('fromUser', 'recipients.user', 'attachments');

    $mapped = $message->toArray();
    $mapped['attachments'] = $message->attachments->map(function ($att) {
        $url = null;
        $public = null;
        if ($att->status === 'ready' && $att->path) {
            try {
                $url = route('api.attachments.stream', ['path' => $att->path]);
                $public = asset('storage/' . ltrim($att->path, '/'));
            } catch (\Throwable $__e) {
                $url = null;
                $public = null;
            }
        }
        return [
            'id' => $att->id,
            'original_name' => $att->original_name,
            'mime_type' => $att->mime_type,
            'size' => $att->size,
            'status' => $att->status,
            'url' => $url,
            'public_url' => $public,
            'path' => $att->path,
        ];
    })->values();

    return response()->json($mapped);
})->name('debug.messages.public.payload');

// Local-only debug: stream attachments without auth (useful for diagnosing
// whether the streaming pipeline or auth/session is the cause of 404s).
// Only available in local environment or from loopback to avoid exposure.
Route::get('/debug/public/attachments/stream', function (Request $request) {
    if (!app()->environment('local') && $request->ip() !== '127.0.0.1' && $request->ip() !== '::1') {
        abort(404);
    }

    $path = $request->query('path');
    $id = $request->query('id');
    if (!$path && !$id) {
        return response()->json(['error' => 'path_or_id_required'], 400);
    }

    if ($id) {
        $att = \App\Models\Attachment::find($id);
        if (!$att) return response()->json(['error' => 'not_found'], 404);
        $path = $att->path;
    }

    $svc = new \App\Services\AttachmentService();
    try {
        $full = $svc->diskPath((string)$path);
        return response()->file($full);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'not_found'], 404);
    }
})->name('debug.attachments.public.stream');

Route::middleware(['web', 'auth:sanctum'])->post('/chat/messages/{message}/read', [\App\Http\Controllers\Chat\ChatController::class, 'markAsRead']);

// JobBox lightweight fetch for event-driven previews
// These endpoints are SPA-facing and need the session + CSRF handling provided
// by the `web` middleware so browser XHRs using first-party cookies work.
Route::middleware(['web', 'auth:sanctum'])->get('/jobbox/{id}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'apiShow'])->name('api.jobbox.show');
// Mark a JobAssignmentMessage as read (SPA-friendly JSON endpoint)
Route::middleware(['web', 'auth:sanctum'])->post('/jobbox/{id}/read', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'apiMarkRead'])->name('api.jobbox.read');

// End of API route definitions
