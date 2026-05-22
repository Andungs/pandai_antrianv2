<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class DisplayController extends Controller
{
    /**
     * State Display TV — semua loket aktif beserta antrean yang dipanggil.
     * GET /api/guest/display
     */
    public function index(): JsonResponse
    {
        $counters = Counter::with(['services', 'users'])
            ->where('status', true)
            ->orderBy('name')
            ->get()
            ->map(function (Counter $counter) {
                // Antrean yang sedang dipanggil ATAU dilayani di loket ini (prioritas: dipanggil > dilayani)
                $currentQueue = Queue::today()
                    ->where('counter_id', $counter->id)
                    ->whereIn('status', ['dipanggil', 'dilayani'])
                    ->latest('called_at')
                    ->first();

                return [
                    'id'            => $counter->id,
                    'name'          => $counter->name,
                    'service_name'  => $counter->services->pluck('name')->join(', '),
                    'officer_name'  => $counter->users->pluck('name')->join(', '),
                    'current_queue' => $currentQueue ? [
                        'queue_number' => $currentQueue->queue_number,
                        'status'       => $currentQueue->status,
                        'called_at'    => $currentQueue->called_at,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => [
                'counters' => $counters,
                'settings' => [
                    'app_name' => Setting::get('app_name', 'Pandai Antrian'),
                    'app_logo' => Setting::get('app_logo')
                        ? asset('storage/' . Setting::get('app_logo'))
                        : null,
                ],
            ],
        ]);
    }

    /**
     * Daftar layanan aktif untuk KIOSK.
     * GET /api/guest/services
     */
    public function services(): JsonResponse
    {
        $services = Service::select('id', 'name', 'prefix_code')
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) {
                $waitingCount = Queue::today()
                    ->where('service_id', $service->id)
                    ->where('status', 'menunggu')
                    ->count();

                return [
                    'id'            => $service->id,
                    'name'          => $service->name,
                    'prefix_code'   => $service->prefix_code,
                    'waiting_count' => $waitingCount,
                ];
            });

        $settings = [
            'app_name'      => Setting::get('app_name', 'Pandai Antrian'),
            'app_logo'      => Setting::get('app_logo')
                ? asset('storage/' . Setting::get('app_logo'))
                : null,
            'enable_camera' => Setting::get('enable_camera', 'false') === 'true',
            'printer_name'  => Setting::get('printer_name', ''),
        ];

        return response()->json([
            'data' => [
                'services' => $services,
                'settings' => $settings,
            ],
        ]);
    }
}
