<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan sesi yang masih terbuka ketika karyawannya dinonaktifkan.
 */
class EnsureEmployeeIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user()?->employee;

        if ($employee && ! $employee->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Akun Anda sudah dinonaktifkan. Hubungi HRD untuk informasi lebih lanjut.');
        }

        return $next($request);
    }
}
