<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Akses ditolak. Hanya Admin yang dapat mengakses menu ini.'], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', 'Akses ditolak: menu Akun & Settings hanya dapat dibuka oleh Admin.');
        }

        return $next($request);
    }
}

