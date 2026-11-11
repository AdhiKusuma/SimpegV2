<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbNotifikasi;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $userId = (string) $request->session()->get('id_user', '');

        $notifications = TbNotifikasi::query()
            ->where('id_user', $userId)
            ->orderByDesc('waktu_notif')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TbNotifikasi $notification): array => $this->notifications->present($notification));

        $this->notifications->markAllRead($userId);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function open(Request $request, TbNotifikasi $notification): RedirectResponse
    {
        $userId = (string) $request->session()->get('id_user', '');

        if ($userId === '' || (string) $notification->id_user !== $userId) {
            throw new HttpException(403, 'Notifikasi tidak dapat diakses.');
        }

        $this->notifications->markReadForUser($notification->id_notif, $userId);
        $destination = $this->notifications->actionUrl($notification->link_aksi);

        return redirect()->to($destination ?: route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead((string) $request->session()->get('id_user', ''));

        return redirect()->back();
    }

    public function destroyRead(Request $request): RedirectResponse
    {
        $userId = (string) $request->session()->get('id_user', '');
        $deleted = $this->notifications->deleteReadForUser($userId);

        return redirect()
            ->route('notifications.index')
            ->with('success', $deleted > 0
                ? number_format($deleted, 0, ',', '.').' notifikasi yang sudah dibaca berhasil dihapus.'
                : 'Tidak ada notifikasi yang sudah dibaca untuk dihapus.');
    }
}
