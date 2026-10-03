<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use Carbon\Carbon;
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
     * Histori tren antrean harian (default 7 hari terakhir, filter rentang maks 7 hari).
     * Hanya hari yang memiliki data yang dikembalikan.
     * GET /api/admin/dashboard/history?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $to   = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : now()->startOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(6);

        if ($from->gt($to)) {
            return response()->json(['message' => 'Tanggal awal tidak boleh melebihi tanggal akhir.'], 422);
        }
        if ($from->diffInDays($to) > 6) {
            return response()->json(['message' => 'Rentang tanggal maksimal 7 hari.'], 422);
        }

        $history = Queue::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as waiting"),
                DB::raw("SUM(CASE WHEN status = 'dilayani' THEN 1 ELSE 0 END) as served"),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'    => Carbon::parse($row->date)->toDateString(),
                'total'   => (int) $row->total,
                'waiting' => (int) $row->waiting,
                'served'  => (int) $row->served,
            ]);

        return response()->json([
            'data'    => $history,
            'message' => 'OK',
            'range'   => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    /**
     * Rata-rata waktu melayani & waktu tunggu per layanan (data pendukung penambahan loket).
     * Waktu layanan = served_at - called_at; waktu tunggu = called_at - created_at.
     * Perhitungan dilakukan di PHP agar kompatibel di semua driver database.
     * GET /api/admin/dashboard/service-time?from=YYYY-MM-DD&to=YYYY-MM-DD (maks 7 hari)
     */
    public function serviceTime(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $to   = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : now()->startOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(6);

        if ($from->gt($to)) {
            return response()->json(['message' => 'Tanggal awal tidak boleh melebihi tanggal akhir.'], 422);
        }
        if ($from->diffInDays($to) > 6) {
            return response()->json(['message' => 'Rentang tanggal maksimal 7 hari.'], 422);
        }

        $rows = Queue::with('service:id,name,prefix_code')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'dilayani')
            ->whereNotNull('called_at')
            ->whereNotNull('served_at')
            ->get(['id', 'service_id', 'created_at', 'called_at', 'served_at']);

        $data = $rows->groupBy('service_id')->map(function ($group) {
            $service = $group->first()->service;
            $serviceMin = $group->avg(fn ($q) => max(0, $q->called_at->diffInSeconds($q->served_at, false)) / 60);
            $waitMin    = $group->avg(fn ($q) => max(0, $q->created_at->diffInSeconds($q->called_at, false)) / 60);

            return [
                'service_name'     => $service?->name ?? '-',
                'prefix_code'      => $service?->prefix_code ?? '-',
                'served'           => $group->count(),
                'avg_service_time' => round($serviceMin, 1),
                'avg_wait_time'    => round($waitMin, 1),
            ];
        })->sortByDesc('avg_service_time')->values();

        return response()->json([
            'data'    => $data,
            'message' => 'OK',
            'range'   => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
