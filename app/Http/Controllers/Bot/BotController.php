<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSummary;
use App\Models\AiSetting;

class BotController extends Controller
{
    // POST /bot/chat
    public function chat(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'system_prompt' => 'nullable|string|max:2000',
            'files' => 'nullable|array',
            'files.*.path' => 'nullable|string',
            'files.*.original_name' => 'nullable|string',
            'conversation_id' => 'nullable|integer|exists:ai_conversations,id',
        ]);

        $key = env('VITE_OPENAI_API_KEY') ?: env('OPENAI_API_KEY');
        if (!$key) {
            return response()->json(['error' => 'OpenAI API key not configured'], 500);
        }

        try {
            $messages = [];
            // keep track of how many chars we included from uploaded files (always defined)
            $totalCharsIncluded = 0;
            // system prompt precedence: provided prompt else admin-configured else default
            $setting = AiSetting::latest()->first();
            $defaultSystem = 'You are a helpful assistant. Reply concisely in Japanese when appropriate.';
            // Guard against missing AiSetting record to avoid null property access
            if (!empty($data['system_prompt'])) {
                $system = $data['system_prompt'];
            } else {
                $system = $defaultSystem;
                if ($setting && isset($setting->system_prompt) && $setting->system_prompt) {
                    $system = $setting->system_prompt;
                }
            }
            $messages[] = ['role' => 'system', 'content' => $system];
            // if files are attached, include a summary and (when safe) the file contents or preview
            if (!empty($data['files']) && is_array($data['files'])) {
                $fileDesc = [];
                // safety: limit total characters included from files to 24k
                $maxTotalChars = 24000; // avoid sending too much
                // per-file hard limit
                $maxPerFile = 8000;
                foreach ($data['files'] as $f) {
                    $name = $f['original_name'] ?? $f['path'] ?? 'unknown';
                    $fileDesc[] = $name;
                    // try to include readable text content when possible
                    $path = $f['path'] ?? null;
                    $includedText = null;
                    try {
                        if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                            $mime = $f['mime'] ?? null;
                            if (!$mime) {
                                $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
                                if (file_exists($fullPath)) {
                                    $mime = mime_content_type($fullPath) ?: null;
                                }
                            }
                            $isText = is_string($mime) && str_starts_with($mime, 'text/');
                            $ext = pathinfo($path, PATHINFO_EXTENSION);
                            $likelyTextExt = in_array(strtolower($ext), ['txt','md','csv','json','html','xml','log']);
                            if ($isText || $likelyTextExt) {
                                $contents = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
                                // include at most per-file limit and guard total
                                $snippet = mb_substr($contents, 0, $maxPerFile);
                                $truncated = mb_strlen($contents) > $maxPerFile ? "\n\n[...file truncated]" : '';
                                if ($totalCharsIncluded + mb_strlen($snippet) > $maxTotalChars) {
                                    // not enough budget to include full snippet; include smaller excerpt
                                    $remaining = max(0, $maxTotalChars - $totalCharsIncluded);
                                    if ($remaining > 200) {
                                        $snippet = mb_substr($snippet, 0, $remaining);
                                        $truncated = "\n\n[...file truncated due to total size limit]";
                                        $includedText = $snippet . $truncated;
                                        $totalCharsIncluded += mb_strlen($snippet);
                                    } else {
                                        $includedText = null;
                                    }
                                } else {
                                    $includedText = $snippet . $truncated;
                                    $totalCharsIncluded += mb_strlen($snippet);
                                }
                            } elseif (!empty($f['preview'])) {
                                $includedText = $f['preview'];
                                $totalCharsIncluded += mb_strlen($includedText);
                            }
                        } elseif (!empty($f['preview'])) {
                            $includedText = $f['preview'];
                            $totalCharsIncluded += mb_strlen($includedText);
                        }
                    } catch (\Exception $e) {
                        // ignore reading errors but log
                        \Illuminate\Support\Facades\Log::warning('BotController file include failed: '.$e->getMessage());
                    }

                    if ($includedText) {
                        $messages[] = ['role' => 'system', 'content' => "File: {$name}\n\n" . $includedText];
                    } else {
                        // if we couldn't include text, still inform the model of the file presence
                        $messages[] = ['role' => 'system', 'content' => "File available: {$name} (content not included). You may request the user for details if needed."];
                    }
                }
                // also provide quick listing for reference
                $messages[] = ['role' => 'system', 'content' => 'The user has uploaded the following files: ' . implode(', ', $fileDesc) . '.'];
            }
            $messages[] = ['role' => 'user', 'content' => $data['message']];

            // If a conversation_id was provided, include previous messages from that conversation
            // to give the model conversational context. We first try to include a stored conversation
            // summary (ai_summaries) if one exists, then fetch recent messages in reverse-order
            // batches and stop when a conservative character budget is reached (approximate token budget).
            if (!empty($data['conversation_id'])) {
                try {
                    $convId = $data['conversation_id'];
                    // try to surface a saved summary attached to the conversation (fast path)
                    $summaryContent = null;
                    try {
                        $convModel = AiConversation::find($convId);
                        if ($convModel && !empty($convModel->summary_id)) {
                            $summary = AiSummary::find($convModel->summary_id);
                            if ($summary && !empty($summary->summary)) {
                                $summaryContent = "Conversation summary (up to message #{$summary->summarized_until_message_id}):\n" . $summary->summary;
                            }
                        }
                    } catch (\Throwable $_e) {
                        // non-fatal: continue to history aggregation even if summary lookup fails
                        \Illuminate\Support\Facades\Log::warning('BotController: summary lookup failed: ' . $_e->getMessage());
                    }
                    $historyCharBudget = 40000; // approx for ~10000 tokens
                    $accum = 0;
                    $toInclude = [];
                    $batchSize = 50;
                    $lastId = null;
                    $done = false;

                    while (!$done) {
                        // prefer using precomputed char_count if available to avoid repeated mb_strlen calls
                        $q = AiMessage::where('ai_conversation_id', $convId)->select(['id', 'role', 'content', 'char_count'])->orderBy('id', 'desc')->limit($batchSize);
                        if ($lastId) $q->where('id', '<', $lastId);
                        $batch = $q->get();
                        if ($batch->isEmpty()) break;
                        foreach ($batch as $m) {
                            $content = $m->content ?? '';
                            if ($content === null || trim($content) === '') continue;
                            $len = 0;
                            if (isset($m->char_count) && $m->char_count) {
                                $len = (int) $m->char_count;
                            } else {
                                $len = mb_strlen($content);
                            }
                            if ($accum + $len > $historyCharBudget) {
                                $done = true;
                                break;
                            }
                            $toInclude[] = ['role' => ($m->role ?? 'user'), 'content' => $content];
                            $accum += $len;
                        }
                        $lastId = $batch->last()->id;
                        if ($batch->count() < $batchSize) break;
                    }

                    if (!empty($toInclude)) {
                        // restore chronological order
                        $toInclude = array_reverse($toInclude);
                        // remove the current user message appended earlier (we'll re-add it at the end)
                        array_pop($messages);
                        // if we fetched a stored summary, insert it before the expanded history
                        if (!empty($summaryContent)) {
                            $messages[] = ['role' => 'system', 'content' => $summaryContent];
                        }
                        foreach ($toInclude as $h) {
                            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
                        }
                        // re-add current user message
                        $messages[] = ['role' => 'user', 'content' => $data['message']];
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('BotController: failed to include conversation history: '.$e->getMessage());
                }
            }

            // prefer admin-configured model and max_tokens when available
            $model = 'gpt-3.5-turbo';
            $maxTokens = 800;
            if ($setting) {
                if (isset($setting->model) && $setting->model) {
                    $model = $setting->model;
                }
                if (isset($setting->max_tokens) && $setting->max_tokens) {
                    $maxTokens = $setting->max_tokens;
                }
            }
            // enforce a safe hard cap to avoid runaway tokens (configurable via config/reverb.php)
            $hardMax = config('reverb.ai_max_tokens_hardcap', 2000);
            if ($maxTokens > $hardMax) {
                $maxTokens = $hardMax;
            }
            // temperature: allow numeric override from model_options stored as array or JSON
            $temperature = 0.6;
            if ($setting && isset($setting->model_options)) {
                $modelOptions = null;
                if (is_array($setting->model_options)) {
                    $modelOptions = $setting->model_options;
                } elseif (is_string($setting->model_options) && $setting->model_options !== '') {
                    try {
                        $decoded = json_decode($setting->model_options, true);
                        if (is_array($decoded)) $modelOptions = $decoded;
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                if (is_array($modelOptions) && array_key_exists('temperature', $modelOptions)) {
                    $temperature = $modelOptions['temperature'];
                }
            }

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ];

            // log chosen model/limits for auditing
            \Illuminate\Support\Facades\Log::info('AI request', ['model' => $model, 'max_tokens' => $maxTokens, 'temperature' => $temperature, 'chars_included_from_files' => $totalCharsIncluded]);

            $resp = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$resp->successful()) {
                // Log detailed info for debugging
                try {
                    \Illuminate\Support\Facades\Log::error('OpenAI API non-success response', [
                        'status' => $resp->status(),
                        'body' => $resp->body(),
                        'payload_summary' => substr(json_encode($payload), 0, 2000),
                    ]);
                } catch (\Throwable $_e) {
                    // swallow logging errors
                }

                // Try to extract a friendly error message from the OpenAI response body
                $detailBody = $resp->body();
                $friendly = 'OpenAI API request failed';
                try {
                    $djson = json_decode($detailBody, true);
                    if (is_array($djson) && isset($djson['error']['message'])) {
                        $friendly = $djson['error']['message'];
                    }
                } catch (\Throwable $_e) {
                    // ignore
                }

                $statusCode = $resp->status() ?: 500;
                return response()->json(['error' => $friendly, 'status' => $statusCode, 'detail' => $detailBody], $statusCode);
            }

            $json = [];
            try {
                $json = $resp->json() ?: [];
            } catch (\Throwable $_e) {
                // log parse issue but continue
                \Illuminate\Support\Facades\Log::warning('Failed to parse OpenAI response JSON', ['body' => $resp->body()]);
            }
            $reply = '';
            if (is_array($json) && isset($json['choices']) && is_array($json['choices']) && isset($json['choices'][0]['message']['content'])) {
                $reply = $json['choices'][0]['message']['content'];
            }

            // persist conversation + messages for history
            try {
                if (!empty($data['conversation_id'])) {
                    $conv = AiConversation::find($data['conversation_id']);
                    // if not found fallback to create
                    if (!$conv) {
                        $conv = AiConversation::create([
                            'user_id' => $request->user()?->id,
                            'title' => mb_substr($data['message'], 0, 120),
                            'system_prompt' => $system,
                        ]);
                    }
                } else {
                    $conv = AiConversation::create([
                        'user_id' => $request->user()?->id,
                        'title' => mb_substr($data['message'], 0, 120),
                        'system_prompt' => $system,
                    ]);
                }

                // store user message
                AiMessage::create([
                    'ai_conversation_id' => $conv->id,
                    'user_id' => $request->user()?->id,
                    'role' => 'user',
                    'content' => $data['message'],
                ]);

                // store any system messages we added about files
                foreach ($messages as $m) {
                    if ($m['role'] === 'system' && str_starts_with($m['content'], 'File:')) {
                        AiMessage::create([
                            'ai_conversation_id' => $conv->id,
                            'role' => 'system',
                            'content' => $m['content'],
                        ]);
                    }
                }

                // store assistant reply
                AiMessage::create([
                    'ai_conversation_id' => $conv->id,
                    'role' => 'assistant',
                    'content' => $reply,
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('AI history save failed: '.$e->getMessage());
            }

            return response()->json(['reply' => $reply, 'raw' => $json]);
        } catch (\Exception $ex) {
            return response()->json(['error' => 'Request error', 'message' => $ex->getMessage()], 500);
        }
    }
}
