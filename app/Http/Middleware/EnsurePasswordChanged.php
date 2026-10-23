<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun dengan kata sandi awal (dari onboarding) wajib mengganti kata sandi sebelum memakai aplikasi.
 * Hanya halaman keamanan, simpan kata sandi, konfirmasi kata sandi, dan keluar yang tetap bisa dibuka.
 */
class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = [
        'security.edit', 'user-password.update', 'logout', 'password.confirm', 'password.confirm.store', 'password.confirmation',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->must_change_password || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Ganti kata sandi awal Anda terlebih dahulu.');
        }

        return redirect()->route('security.edit');
    }
}
