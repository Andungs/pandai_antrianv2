<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::withCount('counters');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $request->filled('per_page')
            ? $query->orderBy('name')->paginate($request->per_page)
            : $query->orderBy('name')->get();

        return response()->json(['data' => $services]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'prefix_code'  => 'required|string|max:5|unique:services,prefix_code',
            'digit_length' => 'required|integer|min:1|max:5',
        ]);

        $service = Service::create($request->only('name', 'prefix_code', 'digit_length'));

        return response()->json(['data' => $service], 201);
    }

    public function show(int $id): JsonResponse
    {
        $service = Service::withCount('counters')->findOrFail($id);
        return response()->json(['data' => $service]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'name'         => 'required|string|max:100',
            'prefix_code'  => 'required|string|max:5|unique:services,prefix_code,' . $id,
            'digit_length' => 'required|integer|min:1|max:5',
        ]);

        $service->update($request->only('name', 'prefix_code', 'digit_length'));

        return response()->json(['data' => $service]);
    }

    public function destroy(int $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        // Cek apakah ada antrean hari ini
        if ($service->queues()->where('date', now()->toDateString())->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menghapus layanan yang masih memiliki antrean hari ini.',
            ], 422);
        }

        $service->delete();
        return response()->json(['message' => 'Layanan berhasil dihapus.']);
    }
}
