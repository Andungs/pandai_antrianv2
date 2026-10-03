<?php

namespace App\Events;

use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Queue $queue;

    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Public channel — tracking page & display listen tanpa auth.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('queue.updates'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->queue->id,
            'queue_number' => $this->queue->queue_number,
            'service_id'   => $this->queue->service_id,
            'status'       => $this->queue->status,
            'counter_name' => $this->queue->counter?->name,
            'called_at'    => $this->queue->called_at?->toISOString(),
            'served_at'    => $this->queue->served_at?->toISOString(),
            'waiting_by_counter' => $this->waitingByCounter(),
            'total_waiting' => Queue::today()->where('status', 'menunggu')->count(),
        ];
    }

    /**
     * Sisa antrean menunggu hari ini per loket aktif (counter_id => jumlah),
     * dikirim lewat WebSocket agar Display TV tidak perlu polling/fetch.
     */
    private function waitingByCounter(): array
    {
        $waitingPerService = Queue::today()
            ->where('status', 'menunggu')
            ->selectRaw('service_id, COUNT(*) as total')
            ->groupBy('service_id')
            ->pluck('total', 'service_id');

        return Counter::with('services:id')
            ->where('status', true)
            ->get()
            ->mapWithKeys(fn (Counter $counter) => [
                $counter->id => (int) $counter->services->sum(fn ($s) => $waitingPerService[$s->id] ?? 0),
            ])
            ->all();
    }
}
