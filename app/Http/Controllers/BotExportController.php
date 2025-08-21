<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BotExportController extends Controller
{
    // POST /bot/export
    public function export(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'messages' => 'nullable|array',
            'format' => 'required|string|in:txt,md,doc'
        ]);

        $title = $data['title'] ?? ('conversation_' . date('Ymd_His'));
        // sanitize filename
        $safe = preg_replace('/[^A-Za-z0-9_\-\s\p{Han}\p{Hiragana}\p{Katakana}]/u', '_', $title);
        $safe = Str::substr($safe, 0, 120);
        $timestamp = date('Ymd_His');
        $ext = $data['format'];
        $filename = "{$timestamp}_{$safe}.{$ext}";
        $path = "exports/{$filename}";

        $messages = $data['messages'] ?? [];
        $system = $data['system_prompt'] ?? '';

        // build content based on format
        $content = '';
        if ($ext === 'txt') {
            $content .= $title . "\n\n";
            if ($system) {
                $content .= "SYSTEM:\n" . strip_tags($system) . "\n\n";
            }
            foreach ($messages as $m) {
                $role = $m['role'] ?? ($m['user'] ?? 'user');
                $text = isset($m['content']) ? $m['content'] : ($m['text'] ?? '');
                $content .= strtoupper($role) . ":\n" . strip_tags($text) . "\n\n";
            }
        } elseif ($ext === 'md') {
            // Fancy markdown: big title with emoji, system prompt section, role icons, and fenced code blocks when detected
            $content .= "# " . $title . " ✨\n\n";
            if ($system) {
                $content .= "---\n\n";
                $content .= "## ⚙️ System Prompt\n\n" . trim($system) . "\n\n";
            }

            // helper: detect code-like content
            $isCode = function ($txt) {
                if (!$txt) return false;
                if (strpos($txt, '```') !== false) return true;
                // common code keywords
                if (preg_match('/\b(function|class|def|import|const|let|var|public|private|console\.log|printf)\b/i', $txt)) return true;
                // arrow functions or braces/semicolons suggest code
                if (strpos($txt, '=>') !== false || strpos($txt, ";\n") !== false || strpos($txt, "{\n") !== false) return true;
                return false;
            };

            $roleEmoji = [
                'user' => '👤',
                'assistant' => '🤖',
                'system' => '⚙️',
            ];

            foreach ($messages as $m) {
                $roleKey = strtolower($m['role'] ?? ($m['user'] ?? 'user'));
                $emoji = $roleEmoji[$roleKey] ?? '';
                $displayRole = ucfirst($roleKey);
                $text = isset($m['content']) ? $m['content'] : ($m['text'] ?? '');
                $text = rtrim($text, "\n");

                $content .= "### " . ($emoji ? ($emoji . ' ') : '') . $displayRole . "\n\n";

                if ($isCode($text)) {
                    // ensure fenced code block; if user already provided ``` keep as is
                    if (strpos($text, '```') === false) {
                        $content .= "```\n" . $text . "\n```\n\n";
                    } else {
                        $content .= $text . "\n\n";
                    }
                } else {
                    // normal text, preserve newlines
                    $content .= trim($text) . "\n\n";
                }

                $content .= "---\n\n";
            }
        } else {
            // doc: produce simple HTML and save with .doc extension so Word can open it
            // produce HTML doc styled for Word: large headings, emojis, and dark-styled code blocks
            $css = '<style>body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial; padding:20px; color:#111} h1{font-size:28pt;margin-bottom:8px} h2{font-size:18pt;color:#333} h3{font-size:14pt;color:#222;margin-top:12px} .system{background:#f7f7f9;padding:10px;border-left:4px solid #ddd} pre.code{background:#0b0b0b;color:#e6e6e6;padding:12px;border-radius:6px;overflow:auto} .meta{font-size:11pt;color:#666;margin-bottom:8px}</style>';
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>' . $css . '</head><body>';
            $html .= '<h1>' . e($title) . ' ✨</h1>';
            if ($system) {
                $html .= '<h2>⚙️ System Prompt</h2><div class="system">' . nl2br(e($system)) . '</div>';
            }
            foreach ($messages as $m) {
                $role = $m['role'] ?? ($m['user'] ?? 'user');
                $text = isset($m['content']) ? $m['content'] : ($m['text'] ?? '');
                $emoji = ($role === 'assistant' ? '🤖' : ($role === 'system' ? '⚙️' : '👤'));
                $html .= '<h3>' . e($emoji . ' ' . ucfirst($role)) . '</h3>';
                // if code-like, wrap in pre.code
                if (strpos($text, '```') !== false || preg_match('/\b(function|class|def|import|const|let|var|public|private|console\.log)\b/i', $text)) {
                    // strip markdown fences if present
                    $clean = preg_replace('/^```[\s\S]*?```$/m', '', $text);
                    $html .= '<pre class="code"><code>' . e($clean) . '</code></pre>';
                } else {
                    $html .= '<div>' . nl2br(e($text)) . '</div>';
                }
            }
            $html .= '</body></html>';
            $content = $html;
        }

        // store file
        Storage::put($path, $content);

        // return download URL (route)
        $url = route('bot.export.download', ['filename' => $filename]);

        return response()->json(['ok' => true, 'url' => $url, 'filename' => $filename]);
    }

    // GET /bot/export/download/{filename}
    public function download($filename)
    {
        $san = basename($filename); // prevent directory traversal
        $path = 'exports/' . $san;
        if (!Storage::exists($path)) {
            abort(404);
        }
        return Storage::download($path, $san);
    }
}
