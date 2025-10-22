<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attachment;
use Illuminate\Support\Facades\Schema;

class MigrateAttachmentOwners extends Command
{
    protected $signature = 'attachments:migrate-owners {--commit : Actually perform updates} {--batch=500 : Batch size}';
    protected $description = 'Migrate attachment ownership columns to owner_type/owner_id and created_by (dry-run by default)';

    public function handle()
    {
        // If the legacy owner columns have already been removed by migration,
        // this command is obsolete and should exit cleanly instead of failing
        // with SQL errors that reference missing columns.
        if (! Schema::hasColumn('attachments', 'owner_type') || ! Schema::hasColumn('attachments', 'owner_id')) {
            $this->info('Legacy owner columns (owner_type/owner_id) not present on attachments table. Nothing to migrate.');
            return 0;
        }

        $commit = $this->option('commit');
        $batch = (int) $this->option('batch');

        $this->info('Scanning attachments for missing owner_type/owner_id...');

        $query = Attachment::query()->where(function ($q) {
            $q->whereNull('owner_type')->orWhereNull('owner_id');
        });

        $total = $query->count();
        $this->info("Found {$total} attachments to inspect.");

        $progress = $this->output->createProgressBar($total > 0 ? $total : 0);
        $progress->start();

        $updated = 0;
        $inspected = 0;

        $query->chunk($batch, function ($items) use (&$progress, &$updated, &$inspected, $commit) {
            foreach ($items as $a) {
                $inspected++;
                $proposedType = null;
                $proposedId = null;

                if ($a->message_id) {
                    $proposedType = 'message';
                    $proposedId = $a->message_id;
                } elseif ($a->diary_id) {
                    $proposedType = 'diary';
                    $proposedId = $a->diary_id;
                } elseif ($a->event_id) {
                    $proposedType = 'event';
                    $proposedId = $a->event_id;
                } elseif ($a->user_id) {
                    $proposedType = 'user';
                    $proposedId = $a->user_id;
                }

                if ($proposedType) {
                    $this->line("Attachment={$a->id} will map to owner_type={$proposedType}, owner_id={$proposedId}");
                    if ($commit) {
                        $a->owner_type = $proposedType;
                        $a->owner_id = $proposedId;
                        if (empty($a->created_by) && $a->user_id) {
                            $a->created_by = $a->user_id;
                        }
                        $a->save();
                        $updated++;
                    }
                } else {
                    $this->line("Attachment={$a->id} has no source columns to map (skipped)");
                }
                $progress->advance();
            }
        });

        $progress->finish();
        $this->line('');
        $this->info("Inspected: {$inspected}, Updated: {$updated}");

        if (!$commit) {
            $this->info('Dry-run complete. Rerun with --commit to apply changes.');
        } else {
            $this->info('Migration complete. Consider running tests and creating migration to drop old columns if safe.');
        }

        return 0;
    }
}
