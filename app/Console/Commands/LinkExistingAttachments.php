<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Attachment;

class LinkExistingAttachments extends Command
{
    protected $signature = 'attachments:link-existing {--commit : Actually perform replacements}';
    protected $description = 'Scan ChatMessage and Diary bodies for legacy file metadata and link them to attachments table (dry-run by default)';

    public function handle()
    {
        $commit = $this->option('commit');
        $this->info('Scanning ChatMessage bodies...');
        $candidates = 0;

        // Scan chat messages
        $messages = \App\Models\ChatMessage::where('type', 'file')->get();
        foreach ($messages as $m) {
            $body = $m->body;
            $parsed = null;
            try {
                $parsed = json_decode($body, true);
            } catch (\Throwable $_) {
                $parsed = null;
            }
            if (is_array($parsed)) {
                $orig = $parsed['original_name'] ?? null;
                $path = $parsed['path'] ?? null;
                $url = $parsed['url'] ?? null;
                // try to find attachment by exact path or original_name
                $att = null;
                if ($path) $att = Attachment::where('path', $path)->first();
                if (!$att && $orig) $att = Attachment::where('original_name', $orig)->first();
                if ($att) {
                    $candidates++;
                    $this->line("Candidate message={$m->id} -> attachment={$att->id} ({$att->path})");
                    if ($commit) {
                        $parsed['attachment_id'] = $att->id;
                        $m->body = json_encode($parsed, JSON_UNESCAPED_UNICODE);
                        $m->save();
                        $this->info("Patched message={$m->id} -> attachment={$att->id}");
                    }
                }
            }
        }

        $this->info("Found {$candidates} candidate(s) in ChatMessage bodies.");

        // Scan Diary entries for inline placeholders like [[attachment:origname]] or JSON
        $this->info('Scanning Diary bodies...');
        $candidates2 = 0;
        $diaries = \App\Models\Diary::all();
        foreach ($diaries as $d) {
            $body = $d->body ?? '';
            // find occurrences of /storage/... or bare filenames in double brackets
            // search for [[attachment:...]] placeholders
            if (preg_match_all('/\[\[attachment:(.*?)\]\]/', $body, $mats)) {
                foreach ($mats[1] as $raw) {
                    $orig = trim($raw);
                    $att = Attachment::where('original_name', $orig)->first();
                    if ($att) {
                        $candidates2++;
                        $this->line("Candidate diary={$d->id} placeholder={$orig} -> attachment={$att->id}");
                        if ($commit) {
                            $body = str_replace("[[attachment:{$raw}]]", json_encode(['attachment_id' => $att->id, 'original_name' => $att->original_name], JSON_UNESCAPED_UNICODE), $body);
                            $d->body = $body;
                            $d->save();
                            $this->info("Patched diary={$d->id} placeholder={$orig} -> attachment={$att->id}");
                        }
                    }
                }
            }
        }

        $this->info("Found {$candidates2} candidate(s) in Diary bodies.");

        $this->info('Done. Use --commit to apply the changes.');
        return 0;
    }
}
