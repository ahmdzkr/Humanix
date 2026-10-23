<?php

namespace App\Http\Middleware;

use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Hanya field yang dipakai UI; model mentah tidak dikirim ke browser.
                'user' => $user?->only(['id', 'name', 'email', 'avatar', 'email_verified_at', 'created_at', 'updated_at']),
                'roleLabel' => $user?->primaryRole()?->value,
                'dashboardUrl' => $user?->dashboardUrl(),
                'hasEmployee' => $user?->employee !== null,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values() : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Lonceng header: jumlah belum dibaca + 5 notifikasi terbaru.
            'notificationCount' => fn () => $user?->unreadNotifications()->count() ?? 0,
            'latestNotifications' => fn () => $user
                ? $user->notifications()->limit(5)->get()->map(fn ($n) => NotificationController::present($n))->all()
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
