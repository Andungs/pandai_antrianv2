<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Dashboard statistik utama.
     * GET /api/admin/dashboard
     */
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        // Statistik hari ini
        $todayStats = Queue::where('date', $today)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as waiting"),
                DB::raw("SUM(CASE WHEN status = 'dipanggil' THEN 1 ELSE 0 END) as called"),
                DB::raw("SUM(CASE WHEN status = 'dilayani' THEN 1 ELSE 0 END) as served"),
                DB::raw("SUM(CASE WHEN status = 'terlewat' THEN 1 ELSE 0 END) as skipped"),
            )
            ->first();

        // Rata-rata waktu layanan (dalam menit)
        $avgServiceTime = Queue::where('date', $today)
            ->where('status', 'dilayani')
            ->whereNotNull('called_at')
            ->whereNotNull('served_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, called_at, served_at)) as avg_minutes'))
            ->value('avg_minutes');

        // Antrean per layanan hari ini
        $perService = Queue::where('date', $today)
            ->join('services', 'queues.service_id', '=', 'services.id')
            ->select(
                'services.name as service_name',
                'services.prefix_code',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN queues.status = 'dilayani' THEN 1 ELSE 0 END) as served"),
                DB::raw("SUM(CASE WHEN queues.status = 'menunggu' THEN 1 ELSE 0 END) as waiting"),
            )
            ->groupBy('services.id', 'services.name', 'services.prefix_code')
            ->get();

        return response()->json([
            'data' => [
                'today' => [
                    'total'            => (int) $todayStats->total,
                    'waiting'          => (int) $todayStats->waiting,
                    'called'           => (int) $todayStats->called,
                    'served'           => (int) $todayStats->served,
                    'skipped'          => (int) $todayStats->skipped,
                    'avg_service_time' => $avgServiceTime ? round((float) $avgServiceTime, 1) : 0,
                ],
                'per_service' => $perService,
            ],
        ]);
    }

    /**
     * Histori tren antrean harian (7 hari terakhir).
     * GET /api/admin/dashboard/history
     */
    public function history(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $startDate = now()->subDays($days - 1)->toDateString();

        $history = Queue::where('date', '>=', $startDate)
            ->select(
                'date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'dilayani' THEN 1 ELSE 0 END) as served"),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $history]);
    }
}
