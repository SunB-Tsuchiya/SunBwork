<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\JobRequest;
use App\Models\Message;
use App\Models\MessageRecipient;
use Carbon\Carbon;

class MigrateJobRequestsToMessages extends Command
{
    protected $signature = 'migrate:job_requests-to-messages {--dry-run} {--batch=100}';

    protected $description = 'Migrate job_requests rows into messages and message_recipients (idempotent).';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $batch = (int) $this->option('batch');

        $this->info("Starting migration of job_requests -> messages (dry-run={$dryRun})");

        $query = JobRequest::where('migrated_to_messages', false);
        $total = $query->count();

        if ($total === 0) {
            $this->info('No job_requests to migrate.');
            return 0;
        }

        $this->info("Found {$total} job_requests to migrate.");

        $processed = 0;

        $query->chunkById($batch, function ($rows) use (&$processed, $dryRun) {
            foreach ($rows as $jr) {
                $this->line("Processing job_request id={$jr->id}");

                // skip if already migrated (double-check for safety)
                if ($jr->migrated_to_messages) {
                    $this->line(" - already marked migrated, skipping");
                    continue;
                }

                $messageData = [
                    'from_user_id' => $jr->from_user_id,
                    'subject' => sprintf('ジョブ割り当て依頼 #%d', $jr->id),
                    'body' => $jr->message ?: '',
                    'status' => $jr->status === 'sent' ? 'sent' : 'draft',
                    'sent_at' => $jr->status === 'sent' ? Carbon::now() : null,
                    'created_at' => $jr->created_at,
                    'updated_at' => $jr->updated_at,
                ];

                if ($dryRun) {
                    $this->line(' - dry-run: would create Message with: ' . json_encode($messageData));
                    $processed++;
                    continue;
                }

                DB::transaction(function () use ($jr, $messageData, &$processed) {
                    $message = Message::create($messageData);

                    // create recipient for to_user_id
                    if ($jr->to_user_id) {
                        MessageRecipient::create([
                            'message_id' => $message->id,
                            'user_id' => $jr->to_user_id,
                            'type' => 'to',
                            'read_at' => null,
                            'created_at' => $jr->created_at,
                            'updated_at' => $jr->updated_at,
                        ]);
                    }

                    // Optionally create a recipient record for the sender as a 'cc' (not required).

                    // Link back
                    $jr->migrated_to_messages = true;
                    $jr->migrated_message_id = $message->id;
                    $jr->save();

                    $processed++;
                    $this->line(" - migrated job_request {$jr->id} -> message {$message->id}");
                });
            }
        });

        $this->info("Migration complete. Processed: {$processed}");
        return 0;
    }
}
