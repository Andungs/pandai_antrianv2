<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login — mengikuti pola pandaicloud auth store.
     * Return token + user data agar frontend bisa set session.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah.',
            ], 401);
        }

        // Revoke existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ]);
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Me — return current user data.
     * Dipanggil oleh frontend saat DashboardLayout mount.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('counter.service');

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Format user data sesuai kebutuhan frontend.
     */
    private function formatUser(User $user): array
    {
        $user->loadMissing('counter.service');

        $data = [
            'id'        => $user->id,
            'name'      => $user->name,
            'username'  => $user->username,
            'role_type' => $user->role_type,
        ];

        // Jika user loket, sertakan info counter
        if ($user->counter) {
            $data['counter'] = [
                'id'           => $user->counter->id,
                'name'         => $user->counter->name,
                'service_id'   => $user->counter->service_id,
                'service_name' => $user->counter->service?->name,
            ];
        }

        // Default route berdasarkan role
        $data['default_route'] = match ($user->role_type) {
            'superadmin' => '/dashboard',
            'loket'      => '/dashboard/loket',
            default      => '/dashboard',
        };

        return $data;
    }
}
