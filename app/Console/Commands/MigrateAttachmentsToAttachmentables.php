<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAttachmentsToAttachmentables extends Command
{
    protected $signature = 'attachments:migrate-to-attachmentables {--commit : Actually write changes, otherwise dry-run}';
    protected $description = 'Migrate existing attachments.diary_id/event_id/message_id into attachmentables polymorphic pivot (dry-run by default)';

    public function handle(): int
    {
        $commit = $this->option('commit');

        $this->info('Starting attachments -> attachmentables migration (' . ($commit ? 'COMMIT' : 'DRY-RUN') . ')');

        $attachments = DB::table('attachments')->select('id', 'diary_id', 'event_id', 'message_id', 'created_at')->get();

        $toInsert = [];
        foreach ($attachments as $a) {
            if ($a->diary_id) {
                $toInsert[] = [
                    'attachment_id' => $a->id,
                    'attachable_type' => 'App\\Models\\Diary',
                    'attachable_id' => $a->diary_id,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->created_at,
                ];
            }
            if ($a->event_id) {
                $toInsert[] = [
                    'attachment_id' => $a->id,
                    'attachable_type' => 'App\\Models\\Event',
                    'attachable_id' => $a->event_id,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->created_at,
                ];
            }
            if ($a->message_id) {
                $toInsert[] = [
                    'attachment_id' => $a->id,
                    'attachable_type' => 'App\\Models\\Message',
                    'attachable_id' => $a->message_id,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->created_at,
                ];
            }
        }

        $this->info('Found ' . count($toInsert) . ' attachmentable links to create');

        if (!$commit) {
            // Show sample (first 20)
            foreach (array_slice($toInsert, 0, 20) as $row) {
                $this->line(json_encode($row));
            }
            $this->info('Dry-run complete. Rerun with --commit to apply changes.');
            return 0;
        }

        DB::beginTransaction();
        try {
            $chunks = array_chunk($toInsert, 500);
            foreach ($chunks as $chunk) {
                DB::table('attachmentables')->insert($chunk);
            }
            DB::commit();
            $this->info('Inserted ' . count($toInsert) . ' rows into attachmentables.');
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error during insertion: ' . $e->getMessage());
            return 1;
        }
    }
}
