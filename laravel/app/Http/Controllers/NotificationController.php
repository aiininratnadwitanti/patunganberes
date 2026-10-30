<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->appNotifications()->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => $notifications->where('is_read', false)->count(),
        ]);
    }

    public function open(AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['is_read' => true]);

        if ($notification->related_type === 'split') {
            $bill = auth()->user()->splitBills()->where('code', $notification->related_id)->first();

            if ($bill) {
                return redirect()->route('split-bills.show', $bill);
            }

            return redirect()->route('history.index');
        }

        if ($notification->related_type === 'iuran') {
            $pool = auth()->user()->iuranPools()->where('code', $notification->related_id)->first();

            if ($pool) {
                return redirect()->route('iuran.index', ['pool' => $pool->id]);
            }

            return redirect()->route('iuran.index');
        }

        return redirect()->route('notifications.index');
    }

    public function markRead(AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['is_read' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->appNotifications()->where('is_read', false)->update(['is_read' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    public function clear(): RedirectResponse
    {
        auth()->user()->appNotifications()->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Semua notifikasi berhasil dihapus.']);
    }

    public function destroy(AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Notifikasi dihapus.']);
    }
}
