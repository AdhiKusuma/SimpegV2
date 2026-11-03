<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbNotifikasi;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
}
