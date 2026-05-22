<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCounterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Counter::with(['services', 'users']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $counters = $request->filled('per_page')
            ? $query->orderBy('name')->paginate($request->per_page)
            : $query->orderBy('name')->get();

        return response()->json(['data' => $counters]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'service_ids'   => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'user_ids'      => 'nullable|array',
            'user_ids.*'    => 'exists:users,id',
            'status'        => 'boolean',
        ]);

        $counter = Counter::create($request->only('name', 'status'));
        
        $counter->services()->sync($request->service_ids);
        if ($request->has('user_ids')) {
            $counter->users()->sync($request->user_ids);
        }

        $counter->load('services', 'users');

        return response()->json(['data' => $counter], 201);
    }

    public function show(int $id): JsonResponse
    {
        $counter = Counter::with(['services', 'users'])->findOrFail($id);
        return response()->json(['data' => $counter]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $counter = Counter::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:100',
            'service_ids'   => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'user_ids'      => 'nullable|array',
            'user_ids.*'    => 'exists:users,id',
            'status'        => 'boolean',
        ]);

        $counter->update($request->only('name', 'status'));
        
        $counter->services()->sync($request->service_ids);
        if ($request->has('user_ids')) {
            $counter->users()->sync($request->user_ids);
        } else {
            $counter->users()->detach();
        }

        $counter->load('services', 'users');

        return response()->json(['data' => $counter]);
    }

    public function destroy(int $id): JsonResponse
    {
        $counter = Counter::findOrFail($id);
        $counter->delete();
        return response()->json(['message' => 'Loket berhasil dihapus.']);
    }

    /**
     * Data dropdown untuk form: daftar layanan & petugas yang tersedia.
     */
    public function formData(): JsonResponse
    {
        $services = Service::select('id', 'name')->orderBy('name')->get();
        $users = User::where('role_type', 'loket')
            ->select('id', 'name', 'username')
            ->orderBy('name')
            ->get();

        return response()->json([
            'services' => $services,
            'users'    => $users,
        ]);
    }
}
