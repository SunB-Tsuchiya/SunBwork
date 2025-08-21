<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class AiHistoryController extends Controller
{
    public function index(Request $request)
    {
        $convs = AiConversation::withCount('messages')->latest()->paginate(20);
        return Inertia::render('Bot/AiHistoryIndex', ['conversations' => $convs]);
    }

    public function show(Request $request, $id)
    {
        $conv = AiConversation::with('messages')->findOrFail($id);
        return Inertia::render('Bot/AiHistoryShow', ['conversation' => $conv]);
    }

    public function showJson(Request $request, $id)
    {
        $conv = AiConversation::with('messages')->findOrFail($id);
        // sanitize message meta before returning
        $arr = $conv->toArray();
        if (!empty($arr['messages']) && is_array($arr['messages'])) {
            foreach ($arr['messages'] as &$m) {
                $m['meta'] = $this->sanitizeMeta($m['meta'] ?? null, $request);
            }
        }
        return response()->json($arr);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'messages' => 'nullable|array'
        ]);

        $conv = AiConversation::create([
            'user_id' => $request->user() ? $request->user()->id : null,
            'title' => $data['title'] ?? null,
            'system_prompt' => $data['system_prompt'] ?? null,
        ]);

        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $m) {
                $meta = $this->sanitizeMeta($m['meta'] ?? null, $request);
                AiMessage::create([
                    'ai_conversation_id' => $conv->id,
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'role' => $m['role'] ?? 'user',
                    'content' => $m['content'] ?? '',
                    'meta' => $meta,
                ]);
            }
        }

        return response()->json($conv);
    }

    public function update(Request $request, $id)
    {
        $conv = AiConversation::findOrFail($id);
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'messages' => 'nullable|array'
        ]);

        // update title/system_prompt if provided
        if (isset($data['title'])) $conv->title = $data['title'];
        if (isset($data['system_prompt'])) $conv->system_prompt = $data['system_prompt'];
        $conv->save();

        // append any new messages (we'll always append the full list for simplicity)
        if (!empty($data['messages'])) {
            // remove existing messages and recreate from provided list to ensure canonical order
            $conv->messages()->delete();
            foreach ($data['messages'] as $m) {
                $meta = $this->sanitizeMeta($m['meta'] ?? null, $request);
                AiMessage::create([
                    'ai_conversation_id' => $conv->id,
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'role' => $m['role'] ?? 'user',
                    'content' => $m['content'] ?? '',
                    'meta' => $meta,
                ]);
            }
        }

        return response()->json($conv->fresh());
    }

    /**
     * Sanitize incoming message meta before saving to DB.
     * Ensures any file.url or file.path uses allowed schemes/locations.
     */
    protected function sanitizeMeta($meta, Request $request)
    {
        if (!$meta || !is_array($meta)) return null;
        // only consider 'file' key for now
        if (isset($meta['file']) && is_array($meta['file'])) {
            $file = $this->sanitizeFileMeta($meta['file']);
            if ($file) return ['file' => $file];
        }
        return null;
    }

    protected function sanitizeFileMeta(array $fileMeta)
    {
        $out = [];
        // allow original_name and mime and size
        if (isset($fileMeta['original_name'])) $out['original_name'] = substr($fileMeta['original_name'], 0, 255);
        if (isset($fileMeta['mime'])) $out['mime'] = substr($fileMeta['mime'], 0, 100);
        if (isset($fileMeta['size'])) $out['size'] = intval($fileMeta['size']);

        // prefer internal path if present and safe
        if (!empty($fileMeta['path'])) {
            $p = ltrim($fileMeta['path'], '\/');
            // only allow paths inside 'bot/' or 'chat/' storage prefixes
            if (Str::startsWith($p, ['bot/', 'chat/', 'attachments/'])) {
                $out['path'] = $p;
                $out['url'] = Storage::url($p);
                return $out;
            }
        }

        // otherwise, allow only http/https URLs that point to our domain or are explicitly allowed hosts
        if (!empty($fileMeta['url']) && filter_var($fileMeta['url'], FILTER_VALIDATE_URL)) {
            try {
                $u = $fileMeta['url'];
                $host = parse_url($u, PHP_URL_HOST);
                // allow if host is empty (relative) or matches our app url host
                $appHost = parse_url(config('app.url') ?? URL::to('/'), PHP_URL_HOST);
                if ($host === $appHost || $host === null) {
                    $out['url'] = $u;
                    return $out;
                }
            } catch (\Exception $e) {
                // fallthrough
            }
        }

        // nothing safe found
        return null;
    }
}
