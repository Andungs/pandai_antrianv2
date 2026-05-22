<?php

namespace App\Events;

use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Queue $queue;
    public Counter $counter;
    public string $action; // 'call' | 'recall'

    public function __construct(Queue $queue, Counter $counter, string $action = 'call')
    {
        $this->queue   = $queue;
        $this->counter = $counter;
        $this->action  = $action;
    }

    /**
     * Public channel — Display TV tidak butuh auth.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('queue.display'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'queue_number'  => $this->queue->queue_number,
            'counter_id'    => $this->counter->id,
            'counter_name'  => $this->counter->name,
            'service_name'  => $this->queue->service->name ?? '',
            'action'        => $this->action,
            'called_at'     => now()->toISOString(),
        ];
    }
}
