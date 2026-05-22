<?php

namespace App\Http\Controllers;

use App\Events\QueueCalled;
use App\Events\QueueUpdated;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    /* ══════════════════════════════════════════════════════════════════════
     *  GUEST — Pengambilan Tiket (KIOSK)
     * ══════════════════════════════════════════════════════════════════════ */

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'photo'      => 'nullable|image|max:2048',
        ]);

        $service = Service::findOrFail($request->service_id);
        $queueNumber = $service->generateNextQueueNumber();

        // Handle foto pengunjung (opsional)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('visitor-photos', 'public');
        }

        $queue = Queue::create([
            'service_id'         => $service->id,
            'date'               => now()->toDateString(),
            'queue_number'       => $queueNumber,
            'status'             => 'menunggu',
            'visitor_photo_path' => $photoPath,
        ]);

        $queue->load('service');

        // Broadcast update agar tracking page bisa refresh
        broadcast(new QueueUpdated($queue))->toOthers();

        return response()->json([
            'data' => [
                'id'            => $queue->id,
                'queue_number'  => $queue->queue_number,
                'service_name'  => $queue->service->name,
                'date'          => $queue->date->toDateString(),
                'status'        => $queue->status,
                'position'      => $this->getQueuePosition($queue),
                'created_at'    => $queue->created_at,
            ],
        ], 201);
    }

    public function track(string $queueNumber): JsonResponse
    {
        $queue = Queue::with('service', 'counter')
            ->today()
            ->where('queue_number', $queueNumber)
            ->first();

        if (! $queue) {
            return response()->json([
                'message' => 'Nomor antrean tidak ditemukan untuk hari ini.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id'            => $queue->id,
                'queue_number'  => $queue->queue_number,
                'service_name'  => $queue->service->name,
                'counter_name'  => $queue->counter?->name,
                'status'        => $queue->status,
                'position'      => $this->getQueuePosition($queue),
                'called_at'     => $queue->called_at,
                'served_at'     => $queue->served_at,
                'created_at'    => $queue->created_at,
            ],
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
     *  LOKET — Operasional Panggilan Antrean
     * ══════════════════════════════════════════════════════════════════════ */

    public function current(Request $request): JsonResponse
    {
        $counter = $this->getUserCounter($request);
        if (! $counter) {
            return response()->json(['message' => 'Anda belum memilih loket manapun.'], 422);
        }

        $currentQueue = Queue::with('service')
            ->today()
            ->where('counter_id', $counter->id)
            ->whereIn('status', ['dipanggil', 'dilayani'])
            ->latest('called_at')
            ->first();

        $serviceIds = $counter->services->pluck('id')->toArray();
        $waitingCount = Queue::waitingFor($serviceIds)->count();

        return response()->json([
            'data' => [
                'counter'       => [
                    'id'           => $counter->id,
                    'name'         => $counter->name,
                    'service_name' => $counter->services->pluck('name')->join(', '),
                ],
                'current_queue' => $currentQueue ? [
                    'id'           => $currentQueue->id,
                    'queue_number' => $currentQueue->queue_number,
                    'service_name' => $currentQueue->service->name,
                    'status'       => $currentQueue->status,
                    'called_at'    => $currentQueue->called_at,
                    'served_at'    => $currentQueue->served_at,
                    'photo_url'    => $currentQueue->visitor_photo_path
                        ? asset('storage/' . $currentQueue->visitor_photo_path)
                        : null,
                ] : null,
                'waiting_count' => $waitingCount,
            ],
        ]);
    }

    public function next(Request $request): JsonResponse
    {
        $counter = $this->getUserCounter($request);
        if (! $counter) {
            return response()->json(['message' => 'Anda belum memilih loket manapun.'], 422);
        }

        // Auto-complete: tandai antrean sebelumnya yang masih "dipanggil" sebagai "dilayani"
        Queue::today()
            ->where('counter_id', $counter->id)
            ->where('status', 'dipanggil')
            ->update(['status' => 'dilayani', 'served_at' => now()]);

        // Ambil antrean berikutnya yang menunggu dari layanan-layanan yang di-handle loket ini
        $serviceIds = $counter->services->pluck('id')->toArray();
        $nextQueue = Queue::waitingFor($serviceIds)->first();

        if (! $nextQueue) {
            return response()->json([
                'message' => 'Tidak ada antrean yang menunggu.',
                'data'    => null,
            ]);
        }

        $nextQueue->update([
            'counter_id' => $counter->id,
            'status'     => 'dipanggil',
            'called_at'  => now(),
        ]);

        $nextQueue->load('service', 'counter');

        // Broadcast ke Display TV
        broadcast(new QueueCalled($nextQueue, $counter, 'call'));
        broadcast(new QueueUpdated($nextQueue));

        return response()->json([
            'data' => [
                'id'            => $nextQueue->id,
                'queue_number'  => $nextQueue->queue_number,
                'service_name'  => $nextQueue->service->name,
                'counter_name'  => $counter->name,
                'called_at'     => $nextQueue->called_at,
                'waiting_count' => Queue::waitingFor($serviceIds)->count(),
            ],
        ]);
    }

    public function recall(Request $request, int $id): JsonResponse
    {
        $counter = $this->getUserCounter($request);
        if (! $counter) {
            return response()->json(['message' => 'Anda belum memilih loket manapun.'], 422);
        }

        $queue = Queue::today()->findOrFail($id);

        if (! in_array($queue->status, ['terlewat', 'dipanggil'])) {
            return response()->json(['message' => 'Antrean ini tidak dapat dipanggil ulang.'], 422);
        }

        // Jika recall dari terlewat, tandai antrean dipanggil saat ini sebagai dilayani
        if ($queue->status === 'terlewat') {
            Queue::today()
                ->where('counter_id', $counter->id)
                ->where('status', 'dipanggil')
                ->update(['status' => 'dilayani', 'served_at' => now()]);
        }

        $queue->update([
            'counter_id' => $counter->id,
            'status'     => 'dipanggil',
            'called_at'  => now(),
        ]);

        $queue->load('service', 'counter');

        broadcast(new QueueCalled($queue, $counter, 'recall'));
        broadcast(new QueueUpdated($queue));

        return response()->json([
            'data' => [
                'id'            => $queue->id,
                'queue_number'  => $queue->queue_number,
                'service_name'  => $queue->service->name,
                'counter_name'  => $counter->name,
                'called_at'     => $queue->called_at,
            ],
        ]);
    }

    public function serve(Request $request, int $id): JsonResponse
    {
        $counter = $this->getUserCounter($request);
        if (! $counter) {
            return response()->json(['message' => 'Anda belum memilih loket manapun.'], 422);
        }

        $queue = Queue::today()
            ->where('counter_id', $counter->id)
            ->where('status', 'dipanggil')
            ->findOrFail($id);

        $queue->update([
            'status'    => 'dilayani',
            'served_at' => now(),
        ]);

        $queue->load('service', 'counter');

        broadcast(new QueueUpdated($queue));

        $serviceIds = $counter->services->pluck('id')->toArray();

        return response()->json([
            'data' => [
                'id'            => $queue->id,
                'queue_number'  => $queue->queue_number,
                'service_name'  => $queue->service->name,
                'counter_name'  => $queue->counter->name,
                'status'        => 'dilayani',
                'called_at'     => $queue->called_at,
                'served_at'     => $queue->served_at,
                'waiting_count' => Queue::waitingFor($serviceIds)->count(),
            ],
        ]);
    }

    public function skip(Request $request, int $id): JsonResponse
    {
        $counter = $this->getUserCounter($request);
        if (! $counter) {
            return response()->json(['message' => 'Anda belum memilih loket manapun.'], 422);
        }

        $queue = Queue::today()
            ->where('counter_id', $counter->id)
            ->where('status', 'dipanggil')
            ->findOrFail($id);

        $queue->update(['status' => 'terlewat']);

        $queue->load('service', 'counter');

        broadcast(new QueueUpdated($queue));

        return response()->json([
            'data' => [
                'id'           => $queue->id,
                'queue_number' => $queue->queue_number,
                'status'       => 'terlewat',
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $counter = $this->getUserCounter($request);
        if (! $counter) {
            return response()->json(['message' => 'Anda belum memilih loket manapun.'], 422);
        }

        $history = Queue::with('service')
            ->today()
            ->where('counter_id', $counter->id)
            ->whereIn('status', ['dilayani', 'terlewat'])
            ->latest('called_at')
            ->get()
            ->map(fn ($q) => [
                'id'           => $q->id,
                'queue_number' => $q->queue_number,
                'service_name' => $q->service->name,
                'status'       => $q->status,
                'called_at'    => $q->called_at,
                'served_at'    => $q->served_at,
            ]);

        return response()->json(['data' => $history]);
    }

    /* ══════════════════════════════════════════════════════════════════════
     *  COUNTER SELECTION (Admin & Loket)
     * ══════════════════════════════════════════════════════════════════════ */

    public function availableCounters(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->role_type === 'superadmin') {
            $counters = Counter::with('services')->where('status', true)->orderBy('name')->get();
        } else {
            $counters = $user->counters()->with('services')->where('status', true)->orderBy('name')->get();
        }

        $counters = $counters->map(fn ($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'service_name' => $c->services->pluck('name')->join(', '),
        ]);

        return response()->json(['data' => $counters]);
    }

    public function selectCounter(Request $request, int $counterId): JsonResponse
    {
        $user = $request->user();
        
        if ($user->role_type === 'superadmin') {
            $counter = Counter::with('services')->where('status', true)->findOrFail($counterId);
        } else {
            $counter = $user->counters()->with('services')->where('status', true)->findOrFail($counterId);
        }

        // Simpan di cache per user
        cache()->put("user_counter_{$user->id}", $counter->id, now()->addHours(12));

        return response()->json([
            'message' => "Anda sekarang bertugas di {$counter->name}.",
            'data'    => [
                'id'           => $counter->id,
                'name'         => $counter->name,
                'service_name' => $counter->services->pluck('name')->join(', '),
            ],
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
     *  HELPERS
     * ══════════════════════════════════════════════════════════════════════ */

    private function getUserCounter(Request $request): ?Counter
    {
        $user = $request->user();

        // Cek cache untuk counter yang dipilih
        $counterId = cache()->get("user_counter_{$user->id}");
        if ($counterId) {
            return Counter::with('services')
                ->where('id', $counterId)
                ->where('status', true)
                ->first();
        }

        // Jika belum memilih tapi petugas loket ini HANYA di-assign ke 1 loket, auto select.
        if ($user->role_type === 'loket') {
            $counters = $user->counters()->where('status', true)->get();
            if ($counters->count() === 1) {
                $counter = $counters->first()->load('services');
                cache()->put("user_counter_{$user->id}", $counter->id, now()->addHours(12));
                return $counter;
            }
        }

        return null;
    }

    private function getQueuePosition(Queue $queue): int
    {
        if ($queue->status !== 'menunggu') {
            return 0;
        }

        return Queue::today()
            ->where('service_id', $queue->service_id)
            ->where('status', 'menunggu')
            ->where('id', '<=', $queue->id)
            ->count();
    }
}
