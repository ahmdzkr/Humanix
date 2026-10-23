<?php

namespace App\Http\Controllers;

use App\Notifications\HrisNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifikasi in-app milik pengguna yang login. Notifikasi orang lain tidak bisa dibuka (404).
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('status') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $request->user()->unreadNotifications() : $request->user()->notifications();

        return Inertia::render('notifications/index', [
            'notifications' => $query->paginate(15)->withQueryString()->through(fn ($n) => self::present($n)),
            'filters' => ['status' => $filter],
            'categories' => HrisNotification::CATEGORIES,
        ]);
    }

    /** Tandai dibaca lalu buka tautannya (hanya path internal). */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /** @return array<string, mixed> */
    public static function present(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'category' => $n->data['category'] ?? 'account',
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'has_url' => filled($n->data['url'] ?? null),
            'read' => $n->read_at !== null,
            'at' => $n->created_at?->toIso8601String(),
        ];
    }
}
