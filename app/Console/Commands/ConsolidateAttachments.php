<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Attachment;
use App\Models\ChatMessage;
use App\Models\Diary;
use Illuminate\Support\Facades\DB;

class ConsolidateAttachments extends Command
{
    protected $signature = 'attachments:consolidate {--dry-run}';
    protected $description = 'Move files from bot/, chat/, diary_attachments/ into attachments/ and update DB records';

    public function handle()
    {
        $dry = $this->option('dry-run');
        $this->info('Starting attachments consolidation' . ($dry ? ' (dry run)' : ''));

        $disk = Storage::disk('public');
        $roots = ['bot', 'chat', 'diary_attachments', 'attachments'];

        foreach ($roots as $root) {
            $this->info("Scanning: $root/");
            try {
                $files = $disk->allFiles($root);
            } catch (\Throwable $e) {
                $this->line("Skipping missing root: $root");
                continue;
            }
            foreach ($files as $file) {
                // skip files that are already under attachments/
                if (Str::startsWith($file, 'attachments/')) continue;
                $this->line("Found: $file");
                $basename = basename($file);
                $datePart = date('Y/m');
                $newName = 'attachments/' . $datePart . '/' . Str::uuid() . '_' . $basename;

                if ($dry) {
                    $this->line("DRY: would move $file -> $newName");
                    continue;
                }

                // Ensure directory exists
                $dir = dirname($newName);
                if (!$disk->exists($dir)) $disk->makeDirectory($dir);

                // Move file
                try {
                    $stream = $disk->readStream($file);
                    $disk->put($newName, $stream);
                    if (is_resource($stream)) fclose($stream);
                    // set visibility
                    try {
                        $disk->setVisibility($newName, 'public');
                    } catch (\Throwable $_) {
                    }
                    // delete old
                    $disk->delete($file);
                } catch (\Throwable $e) {
                    $this->error("Failed to move $file: " . $e->getMessage());
                    continue;
                }

                // Try to find existing Attachment record by path or filename
                $att = Attachment::where('path', $file)->orWhere('original_name', $basename)->first();
                if ($att) {
                    $this->line("Updating Attachment #{$att->id} path -> $newName");
                    $att->path = $newName;
                    $att->save();
                    continue;
                }

                // Otherwise create a new record
                try {
                    // Not all drivers expose mimeType; try where available
                    $size = $disk->size($newName) ?? null;
                    $meta = null;
                    try {
                        // Try to determine mime type using local filesystem path if available
                        $localPath = null;
                        try {
                            $localPath = $disk->path($newName);
                        } catch (\Throwable $_ex) {
                            $localPath = null;
                        }
                        if ($localPath && file_exists($localPath) && function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $meta = finfo_file($finfo, $localPath) ?: null;
                            finfo_close($finfo);
                        }
                    } catch (\Throwable $_e) {
                        $meta = null;
                    }
                    // fallback: infer from extension
                    if (!$meta) {
                        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                        $map = [
                            'txt' => 'text/plain',
                            'md' => 'text/markdown',
                            'pdf' => 'application/pdf',
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'csv' => 'text/csv',
                            'json' => 'application/json'
                        ];
                        $meta = $map[$ext] ?? null;
                    }
                } catch (\Throwable $_e) {
                    $meta = null;
                    $size = null;
                }
                $created = Attachment::create([
                    'path' => $newName,
                    'original_name' => $basename,
                    'mime_type' => $meta,
                    'size' => $size,
                    'status' => 0,
                ]);
                $this->line("Created Attachment #{$created->id} -> $newName");
            }
        }

        // Now scan chat messages and diaries to replace embedded file paths / JSON
        $this->info('Scanning ChatMessage bodies for embedded file metadata');
        $messages = ChatMessage::query()->cursor();
        foreach ($messages as $msg) {
            $body = $msg->body;
            if (!$body) continue;
            $changed = false;
            // Case 1: body is JSON containing file meta
            $trim = trim($body);
            if (str_starts_with($trim, '{') && str_ends_with($trim, '}')) {
                try {
                    $parsed = json_decode($trim, true);
                    if (is_array($parsed) && (isset($parsed['path']) || isset($parsed['url']))) {
                        $oldPath = $parsed['path'] ?? $parsed['url'] ?? null;
                        if ($oldPath) {
                            $maybe = Attachment::where('path', $oldPath)->orWhere('original_name', basename($oldPath))->first();
                            if ($maybe) {
                                $this->line("Message #{$msg->id}: would replace embedded meta with attachment #{$maybe->id}");
                                $newBody = [
                                    'attachment_id' => $maybe->id,
                                    'original_name' => $maybe->original_name,
                                    'mime_type' => $maybe->mime_type,
                                ];
                                $changed = true;
                                if (!$dry) {
                                    $msg->body = json_encode($newBody);
                                    $msg->save();
                                    // ensure pivot exists linking this attachment to the message
                                    try {
                                        if (method_exists($maybe, 'attachTo')) {
                                            $maybe->attachTo($msg);
                                        }
                                    } catch (\Throwable $__e) {
                                        logger()->warning('ConsolidateAttachments: failed to attach Attachment to ChatMessage', ['attachment_id' => $maybe->id, 'chat_message_id' => $msg->id, 'error' => $__e->getMessage()]);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $_e) {
                }
            }

            // Case 2: inline /storage/... links inside text
            if (strpos($body, '/storage/') !== false) {
                $attachmentsToLink = [];
                $updated = preg_replace_callback('#/storage/([^"\s]+)#u', function ($m) use (&$dry, &$changed, $disk, &$attachmentsToLink) {
                    $p = $m[1];
                    $candidate = 'storage/' . $p;
                    $att = Attachment::where('path', $candidate)->first();
                    if ($att) {
                        $changed = true;
                        $attachmentsToLink[] = $att->id;
                        return '/storage/' . ltrim($att->path, '/');
                    }
                    return $m[0];
                }, $body);
                if ($changed && !$dry) {
                    $msg->body = $updated;
                    $msg->save();
                    // attach any found attachments to this message
                    foreach (array_unique($attachmentsToLink) as $aid) {
                        try {
                            $a = Attachment::find($aid);
                            if ($a && method_exists($a, 'attachTo')) {
                                $a->attachTo($msg);
                            }
                        } catch (\Throwable $__e) {
                            logger()->warning('ConsolidateAttachments: failed to attach Attachment after storage link replacement', ['attachment_id' => $aid, 'chat_message_id' => $msg->id, 'error' => $__e->getMessage()]);
                        }
                    }
                }
            }
        }

        $this->info('Scanning Diary entries for [[attachment:{id}:name]] placeholders');
        $diaries = Diary::query()->cursor();
        foreach ($diaries as $d) {
            $content = $d->content ?? '';
            if (strpos($content, '[[attachment:') === false) continue;
            $updated = preg_replace_callback('/\[\[attachment:(\d+):[^\]]+\]\]/', function ($m) use (&$dry, &$changed, $d) {
                $id = intval($m[1]);
                $att = Attachment::find($id);
                if ($att) {
                    // keep placeholder but ensure path is correct; nothing to change in placeholder itself
                    // ensure pivot exists linking this attachment to the diary
                    if (! $dry) {
                        try {
                            if (method_exists($att, 'attachTo')) {
                                $att->attachTo($d);
                            }
                        } catch (\Throwable $__e) {
                            logger()->warning('ConsolidateAttachments: failed to attach Attachment to Diary', ['attachment_id' => $att->id, 'diary_id' => $d->id, 'error' => $__e->getMessage()]);
                        }
                    }
                    return $m[0];
                }
                return $m[0];
            }, $content);
            if ($updated !== $content && !$dry) {
                $d->content = $updated;
                $d->save();
            }
        }

        $this->info('Consolidation complete');
        return 0;
    }
}
