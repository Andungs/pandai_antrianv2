<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('username', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('role_type')) {
            $query->where('role_type', $request->role_type);
        }

        $users = $request->filled('per_page')
            ? $query->orderBy('name')->paginate($request->per_page)
            : $query->orderBy('name')->get();

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'username'  => 'required|string|max:255|unique:users,username',
            'password'  => 'required|string|min:6',
            'role_type' => ['required', Rule::in(['superadmin', 'loket'])],
            'photo'     => 'nullable|image|max:2048',
        ]);

        $data = $request->only('name', 'username', 'role_type');
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('user-photos', 'public');
        }

        $user = User::create($data);

        return response()->json(['data' => $user], 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(['data' => $user]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:255',
            'username'  => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'  => 'nullable|string|min:6',
            'role_type' => ['required', Rule::in(['superadmin', 'loket'])],
            'photo'     => 'nullable|image|max:2048',
        ]);

        $data = $request->only('name', 'username', 'role_type');
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('user-photos', 'public');
        }

        $user->update($data);

        return response()->json(['data' => $user]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        // Jangan biarkan superadmin menghapus dirinya sendiri jika dia satu-satunya? 
        // Boleh skip dulu.
        $user->delete();
        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}
