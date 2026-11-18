<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Notification::class);

        $notifications = Notification::where('user_id', $request->user()?->id)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::where('user_id', $request->user()?->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Gate::authorize('markAllRead', Notification::class);

        Notification::where('user_id', $request->user()?->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Semua pemberitahuan telah ditandai sebagai sudah dibaca.');
    }

    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        $notification = Notification::where('user_id', $request->user()?->id)
            ->findOrFail($notification->id);

        Gate::authorize('markAsRead', $notification);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        $actionUrl = $this->safeActionUrl($notification->action_url);

        if ($actionUrl !== null) {
            return redirect($actionUrl);
        }

        return redirect()->back();
    }

    private function safeActionUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }
}
