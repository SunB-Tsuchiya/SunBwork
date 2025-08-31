<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\JobRequest;

class JobRequestAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $requestModel;

    public function __construct(JobRequest $requestModel)
    {
        $this->requestModel = $requestModel;
    }

    public function broadcastOn()
    {
        $channels = [];
        if (! empty($this->requestModel->to_user_id)) {
            $channels[] = new PrivateChannel('jobrequests.' . $this->requestModel->to_user_id);
        }
        if (! empty($this->requestModel->from_user_id)) {
            $channels[] = new PrivateChannel('jobrequests.' . $this->requestModel->from_user_id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->requestModel->id,
            'status' => $this->requestModel->status,
            'accepted_at' => $this->requestModel->accepted_at?->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
        ];
    }
}
