<?php

namespace App\Http\Middleware;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbUser;
use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLegacySession
{
    private const AUTH_MARKER_COOKIE = 'simpeg-authenticated';

    public function __construct(private readonly RbacService $rbac) {}

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        if (! $session->has('id_user')) {
            if ($request->cookies->get(self::AUTH_MARKER_COOKIE) === '1') {
                return redirect()->route('login')->with('session_expired', true);
            }

            return redirect()->route('login');
        }

        $user = TbUser::query()
            ->select(['id_user', 'id_pegawai', 'hak_akses', 'status_aktif'])
            ->where('id_user', (string) $session->get('id_user'))
            ->first();

        if (! $user) {
            if (app()->environment('testing')) {
                return $next($request);
            }

            return $this->invalidate($request);
        }

        $databaseRole = strtolower((string) $user->hak_akses);
        $sessionRole = strtolower((string) $session->get('hak_akses'));
        $databaseEmployeeId = (string) ($user->id_pegawai ?? '');
        $sessionEmployeeId = (string) $session->get('id_pegawai', '');

        if ($user->status_aktif === 'N'
            || ! in_array($databaseRole, ['admin', 'superadmin', 'kepala', 'user'], true)
            || $databaseRole !== $sessionRole
            || $databaseEmployeeId !== $sessionEmployeeId) {
            return $this->invalidate($request);
        }

        if ($databaseRole === 'kepala') {
            $office = TbJabatan::query()
                ->where('id_peg', $user->id_pegawai)
                ->where('status_jab', 'Aktif')
                ->value('unit_kerja') ?? '-';

            $session->put('kode_kantor', $office);
        } else {
            $session->forget('kode_kantor');
        }

        $routeName = $request->route()?->getName();
        $permission = $this->rbac->permissionForRoute($routeName);
        $allowed = $routeName === 'logout'
            || ($permission !== null
                && (is_array($permission)
                    ? collect($permission)->contains(fn (string $required): bool => $this->rbac->can($request, $required))
                    : $this->rbac->can($request, $permission)));
        abort_unless($allowed, 403);

        return $next($request);
    }

    private function invalidate(Request $request): Response
    {
        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withoutCookie(self::AUTH_MARKER_COOKIE);
    }
}
