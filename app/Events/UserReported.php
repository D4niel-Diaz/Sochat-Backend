<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserReported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Report $report
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.reports'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.reported';
    }

    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->report->report_id,
            'chat_id' => $this->report->chat_id,
            'reporter_guest_id' => $this->report->reporter_guest_id,
            'reported_guest_id' => $this->report->reported_guest_id,
            'reason' => $this->report->reason,
            'status' => $this->report->status,
            'created_at' => $this->report->created_at->toIso8601String(),
        ];
    }
}
