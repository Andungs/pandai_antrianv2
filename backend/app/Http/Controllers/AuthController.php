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
        $user->load('counters.services');

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Format user data sesuai kebutuhan frontend.
     */
    private function formatUser(User $user): array
    {
        $user->loadMissing('counters.services');

        $data = [
            'id'        => $user->id,
            'name'      => $user->name,
            'username'  => $user->username,
            'role_type' => $user->role_type,
        ];

        // Jika user loket, sertakan info counters yang bisa mereka pilih
        if ($user->counters && $user->counters->count() > 0) {
            $data['counters'] = $user->counters->map(function ($counter) {
                return [
                    'id'       => $counter->id,
                    'name'     => $counter->name,
                    'services' => $counter->services->map(fn($s) => [
                        'id'   => $s->id,
                        'name' => $s->name,
                    ]),
                ];
            });
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
