<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * Usage di route: ->middleware('role:superadmin') atau ->middleware('role:loket')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role_type, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Anda tidak memiliki hak akses untuk resource ini.',
            ], 403);
        }

        return $next($request);
    }
}
