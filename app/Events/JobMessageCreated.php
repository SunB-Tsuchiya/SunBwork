<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class JobMessageCreated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;
    public $recipientIds;
    public $jobAssignmentMessageId;
    /**
     * Create a new event instance.
     */
    public function __construct($message, array $recipientIds = [], $jobAssignmentMessageId = null)
    {
        $this->message = $message;
        $this->recipientIds = $recipientIds;
        $this->jobAssignmentMessageId = $jobAssignmentMessageId;
    }

    /**
     * Get the channels the event should broadcast on.
     * Broadcast separately to each recipient's private jobmessages channel.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [];
        foreach ($this->recipientIds as $id) {
            $channels[] = new PrivateChannel('jobmessages.' . $id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        // Provide a lightweight payload that includes the job-assignment-message id
        // and, when possible, a small jam payload so frontend can render a preview
        $payload = [
            'message_id' => $this->message->id ?? null,
            'subject' => $this->message->subject ?? null,
            'from_user_id' => optional($this->message->fromUser)->id ?? ($this->message->from_user_id ?? null),
            'from_user_name' => optional($this->message->fromUser)->name ?? ($this->message->from_user_name ?? null),
            'is_job' => true,
            'job_assignment_message_id' => $this->jobAssignmentMessageId ?? null,
            // Ensure jam is always present (at least an id) so frontend can rely on its shape
            'jam' => [
                'id' => $this->jobAssignmentMessageId ?? ($this->message->id ?? null),
                'subject' => $this->message->subject ?? null,
                'body' => $this->message->body ?? null,
                'sender' => null,
                'project_job_assignment' => null,
                'recipients' => null,
                'read_at' => null,
            ],
        ];

        // If the provided message looks like a JobAssignmentMessage (model or DTO with project_job_assignment),
        // include a minimal jam payload to allow the frontend to render without an extra fetch.
        try {
            if (is_object($this->message)) {
                // Start from the default jam shape and fill available fields
                $jam = $payload['jam'];
                $jam['id'] = $this->jobAssignmentMessageId ?? ($this->message->id ?? $jam['id']);
                $jam['subject'] = $this->message->subject ?? $jam['subject'];
                $jam['body'] = $this->message->body ?? $jam['body'];

                // sender
                if (isset($this->message->fromUser) && is_object($this->message->fromUser)) {
                    $jam['sender'] = ['id' => $this->message->fromUser->id ?? null, 'name' => $this->message->fromUser->name ?? null];
                } elseif (isset($this->message->from_user_name)) {
                    $jam['sender'] = ['name' => $this->message->from_user_name, 'id' => $this->message->from_user_id ?? null];
                }

                // project_job_assignment minimal info when present (handle object or array)
                if (isset($this->message->project_job_assignment)) {
                    $pja = $this->message->project_job_assignment;
                    if (is_object($pja) || is_array($pja)) {
                        $jam['project_job_assignment'] = [
                            'id' => $pja->id ?? ($pja['id'] ?? null),
                            'user' => (isset($pja->user) && (is_object($pja->user) || is_array($pja->user))) ? ['id' => ($pja->user->id ?? ($pja->user['id'] ?? null)), 'name' => ($pja->user->name ?? ($pja->user['name'] ?? null))] : null,
                            'desired_start_date' => $pja->desired_start_date ?? ($pja['desired_start_date'] ?? null),
                        ];
                    }
                }

                // recipients: include user_id and read_at if available for each recipient row
                if (isset($this->message->recipients) && is_iterable($this->message->recipients)) {
                    // Keep recipient info minimal for broadcasts to avoid oversharing
                    $recs = [];
                    foreach ($this->message->recipients as $r) {
                        $rUserId = $r->user_id ?? ($r['user_id'] ?? null);
                        $rReadAt = $r->read_at ?? ($r['read_at'] ?? null);
                        $recs[] = ['user_id' => $rUserId, 'read_at' => $rReadAt];
                    }
                    $jam['recipients'] = $recs;
                }

                $payload['jam'] = $jam;
            }
        } catch (\Throwable $__e) {
            // non-fatal: keep payload as-is
        }

        return $payload;
    }
}
